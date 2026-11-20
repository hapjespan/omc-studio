<?php

namespace App\Services\Exchange;

use App\Enums\CostKind;
use App\Rules\AmountCents;
use App\Rules\BasisPoints;

class CostRecordRowValidator
{
    private const LIMITS = ['code' => 50, 'naam' => 150, 'eigenaar' => 150, 'applicatie' => 150, 'leverancier' => 150, 'kostenplaats' => 50];

    public const KINDS = [
        'leverancierscontract' => CostKind::SupplierContract,
        'personeel' => CostKind::Personnel,
        'project' => CostKind::Project,
        'overig' => CostKind::Other,
    ];

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

        $kind = self::KINDS[mb_strtolower($row['soort'])] ?? null;

        $row['code'] === '' && $fail('import.code_missing');
        $row['naam'] === '' && $fail('import.name_missing');
        $row['eigenaar'] === '' && $fail('import.owner_missing');
        $kind === null && $fail('import.kind_invalid', ['value' => $row['soort']]);

        foreach (self::LIMITS as $column => $max) {
            mb_strlen($row[$column]) > $max && $fail('import.text_max', ['column' => $column, 'max' => $max]);
        }

        $fields = [
            'name' => $row['naam'],
            'kind' => $kind?->value,
            'supplier' => $row['leverancier'] ?: null,
            'owner' => mb_strtolower($row['eigenaar']),
            'cost_centre' => $row['kostenplaats'] ?: null,
            'application' => $row['applicatie'] === '' ? null : mb_strtolower($row['applicatie']),
        ];

        $figureCells = array_filter(array_intersect_key($row, array_flip(['budget', 'prognose', 'run_pct', 'change_pct', 'fte'])), fn ($cell) => $cell !== '');

        if ($row['jaar'] === '' && $figureCells === []) {
            return [$messages, null, $fields];
        }

        if ($row['jaar'] === '') {
            $fail('import.year_missing');
        } elseif (! isset($years[(int) $row['jaar']]) || ! ctype_digit($row['jaar'])) {
            $fail('import.year_invalid', ['value' => $row['jaar']]);
        }

        $budget = $this->parse($row['budget'], 'budget', fn ($text) => AmountCents::parse($text), 'import.amount_invalid', $fail);
        $row['budget'] === '' && $row['jaar'] !== '' && $fail('import.budget_missing');
        $forecast = $this->parse($row['prognose'], 'prognose', fn ($text) => AmountCents::parse($text), 'import.amount_invalid', $fail);
        $run = $this->parse($row['run_pct'], 'run_pct', fn ($text) => BasisPoints::parse($text), 'import.percentage_invalid', $fail);
        $change = $this->parse($row['change_pct'], 'change_pct', fn ($text) => BasisPoints::parse($text), 'import.percentage_invalid', $fail);
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
        $value === null && $fail($key, ['column' => $column, 'value' => $text]);

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
