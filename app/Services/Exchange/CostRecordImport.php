<?php

namespace App\Services\Exchange;

use App\Enums\CostKind;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Rules\AmountCents;
use App\Rules\BasisPoints;
use App\Services\Exchange\Csv\CsvReader;

class CostRecordImport
{
    public const COLUMNS = ['code', 'naam', 'soort', 'leverancier', 'eigenaar', 'kostenplaats', 'applicatie', 'jaar', 'budget', 'prognose', 'run_pct', 'change_pct', 'fte'];

    private const KINDS = [
        'leverancierscontract' => CostKind::SupplierContract,
        'personeel' => CostKind::Personnel,
        'project' => CostKind::Project,
        'overig' => CostKind::Other,
    ];

    private const MAX_FTE_HUNDREDTHS = 999_999;

    /**
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>}
     */
    public function preview(Organisation $organisation, string $path): array
    {
        $years = FiscalYear::where('organisation_id', $organisation->getKey())->pluck('id', 'year')->all();
        $records = CostRecord::with('yearlyFigures')->where('organisation_id', $organisation->getKey())->get()->keyBy('code_normalised');
        $owners = $organisation->owners()->pluck('name_normalised', 'id')->all();
        $applications = $organisation->applications()->pluck('name_normalised', 'id')->all();

        $lines = [];
        $seenYears = [];
        $seenFields = [];
        $totals = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];

        foreach ((new CsvReader($path))->lines() as $number => $cells) {
            if ($number === 1) {
                continue;
            }

            $row = array_combine(self::COLUMNS, array_pad(array_map('trim', $cells), count(self::COLUMNS), ''));
            [$messages, $figures, $fields] = $this->validated($number, $row, $years);
            $code = mb_strtolower($row['code']);

            if ($row['code'] !== '') {
                [$other, $first] = $seenFields[$code] ??= [$number, $fields];

                if ($other !== $number && $first !== $fields) {
                    $messages[] = __('import.duplicate_fields', ['line' => $number, 'code' => $row['code'], 'other' => $other]);
                }

                if ($row['jaar'] !== '') {
                    $key = $code.'|'.$row['jaar'];

                    if (isset($seenYears[$key])) {
                        $messages[] = __('import.duplicate_year', ['line' => $number, 'code' => $row['code'], 'year' => $row['jaar'], 'other' => $seenYears[$key]]);
                    }

                    $seenYears[$key] ??= $number;
                }
            }

            $status = $messages !== [] ? 'error' : $this->status($records->get($code), $fields, $figures, $years[(int) $row['jaar']] ?? null, $owners, $applications);
            $totals[$status === 'error' ? 'errors' : $status]++;
            $lines[] = ['line' => $number, 'status' => $status, 'messages' => $messages];
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, int>  $years
     * @return array{0: list<string>, 1: ?array<string, ?int>, 2: array<string, ?string>}
     */
    private function validated(int $line, array $row, array $years): array
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

    /**
     * @param  array<string, ?string>  $fields
     * @param  ?array<string, ?int>  $figures
     * @param  array<int, string>  $owners
     * @param  array<int, string>  $applications
     */
    private function status(?CostRecord $record, array $fields, ?array $figures, ?int $yearId, array $owners, array $applications): string
    {
        if ($record === null) {
            return 'new';
        }

        $current = [
            'name' => $record->name,
            'kind' => $record->cost_kind->value,
            'supplier' => $record->supplier,
            'owner' => $owners[$record->owner_id] ?? null,
            'cost_centre' => $record->cost_centre,
            'application' => $applications[$record->application_id] ?? null,
        ];

        if ($current !== $fields) {
            return 'updated';
        }

        if ($figures === null) {
            return 'unchanged';
        }

        $figure = $record->yearlyFigures->firstWhere('fiscal_year_id', $yearId);

        return $figure instanceof YearlyFigure && $figure->only(array_keys($figures)) === $figures ? 'unchanged' : 'updated';
    }
}
