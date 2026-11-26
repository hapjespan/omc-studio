<?php

namespace App\Services\Exchange;

use App\Enums\CostKind;
use App\Rules\AmountCents;
use App\Rules\BasisPoints;
use App\Support\FieldLimits;

class CostRecordRowValidator
{
    private const LIMITS = ['code' => FieldLimits::CODE, 'name' => FieldLimits::NAME, 'owner' => FieldLimits::NAME, 'application' => FieldLimits::NAME, 'supplier' => FieldLimits::NAME, 'cost_centre' => FieldLimits::CODE];

    /**
     * @return array<string, CostKind>
     */
    public static function kinds(): array
    {
        $kinds = [];

        foreach (CostKind::cases() as $kind) {
            $kinds[mb_strtolower(Headers::label('cost_kind', $kind->value))] = $kind;
        }

        return $kinds;
    }

    private const MAX_FTE_HUNDREDTHS = 999_999;

    /**
     * @param  array<string, string>  $row
     * @param  array<int, int>  $years
     * @return array{0: list<string>, 1: ?array<string, ?int>, 2: array<string, ?string>}
     */
    public function validate(int $line, array $row, array $years): array
    {
        $messages = [];
        $fail = function (string $key, array $replace = []) use ($line, &$messages) {
            $messages[] = __($key, ['line' => $line, ...$replace]);
        };

        $kind = self::kinds()[mb_strtolower($row['kind'])] ?? null;

        $row['code'] === '' && $fail('import.code_missing');
        $row['name'] === '' && $fail('import.name_missing');
        $row['owner'] === '' && $fail('import.owner_missing');
        $kind === null && $fail('import.kind_invalid', ['value' => $row['kind']]);

        foreach (self::LIMITS as $column => $max) {
            mb_strlen($row[$column]) > $max && $fail('import.text_max', ['column' => Headers::label('cost_record', $column), 'max' => $max]);
        }

        $fields = [
            'name' => $row['name'],
            'kind' => $kind?->value,
            'supplier' => $row['supplier'] === '' ? null : $row['supplier'],
            'owner' => mb_strtolower($row['owner']),
            'cost_centre' => $row['cost_centre'] === '' ? null : $row['cost_centre'],
            'application' => $row['application'] === '' ? null : mb_strtolower($row['application']),
        ];

        $figureCells = array_filter(array_intersect_key($row, array_flip(['budget', 'forecast', 'run_percentage', 'change_percentage', 'fte'])), fn ($cell) => $cell !== '');

        if ($row['year'] === '' && $figureCells === []) {
            return [$messages, null, $fields];
        }

        if ($row['year'] === '') {
            $fail('import.year_missing');
        } elseif (! isset($years[(int) $row['year']]) || ! ctype_digit($row['year'])) {
            $fail('import.year_invalid', ['value' => $row['year']]);
        }

        $budget = $this->parse($row['budget'], 'budget', fn ($text) => AmountCents::parse($text), 'import.amount_invalid', $fail);
        $row['budget'] === '' && $row['year'] !== '' && $fail('import.budget_missing');
        $forecast = $this->parse($row['forecast'], 'forecast', fn ($text) => AmountCents::parse($text), 'import.amount_invalid', $fail);
        $run = $this->parse($row['run_percentage'], 'run_percentage', fn ($text) => BasisPoints::parse($text), 'import.percentage_invalid', $fail);
        $change = $this->parse($row['change_percentage'], 'change_percentage', fn ($text) => BasisPoints::parse($text), 'import.percentage_invalid', $fail);
        $fte = $this->parse($row['fte'], 'fte', fn ($text) => $this->fte($text), 'import.fte_invalid', $fail);

        if ($run !== null && $change !== null && $run + $change > BasisPoints::MAX) {
            $fail('import.split_too_high');
        }

        return [$messages, ['budget_cents' => $budget, 'forecast_cents' => $forecast, 'run_bp' => $run, 'change_bp' => $change, 'fte_hundredths' => $fte], $fields];
    }

    private function parse(string $text, string $column, callable $parser, string $key, callable $fail): ?int
    {
        if ($text === '') {
            return null;
        }

        $value = $parser($text);
        $value === null && $fail($key, ['column' => Headers::label('cost_record', $column), 'value' => $text]);

        return $value;
    }

    private function fte(string $text): ?int
    {
        if (! preg_match('/^(\d+)(?:,(\d{1,2}))?$/', $text, $match)) {
            return null;
        }

        $hundredths = (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');

        return $hundredths <= self::MAX_FTE_HUNDREDTHS ? $hundredths : null;
    }
}
