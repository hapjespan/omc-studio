<?php

namespace App\Services\Exchange;

use App\Models\Application;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\YearlyFigure;
use App\Services\Allocation\FinalisedYears;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use App\Services\Exchange\Csv\CsvReader;
use App\Services\Support\SerialisedWrite;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CostRecordImport
{
    public const COLUMNS = ['code', 'naam', 'soort', 'leverancier', 'eigenaar', 'kostenplaats', 'applicatie', 'jaar', 'budget', 'prognose', 'run_pct', 'change_pct', 'fte'];

    public function __construct(
        private readonly FinalisedYears $finalised,
        private readonly SerialisedWrite $write,
        private readonly AuditContext $context,
        private readonly AuditTrail $trail,
    ) {}

    /**
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>, creates: array<string, list<string>>}
     */
    public function preview(Organisation $organisation, string $path): array
    {
        /** @var array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>, creates: array<string, list<string>>} $report */
        $report = array_diff_key($this->scan($organisation, $path), array_flip(['rows', 'finalised', 'fingerprint']));

        return $report;
    }

    /**
     * @return array{totals: array<string, int>}
     */
    public function commit(Organisation $organisation, string $path): array
    {
        $scan = $this->scan($organisation, $path);

        if ($scan['totals']['errors'] > 0) {
            $this->refuse('import.refused_errors');
        }

        return $this->write->run([$organisation], fn () => $this->context->during('imported', function () use ($organisation, $scan) {
            $data = $this->load($organisation);

            if ($this->fingerprint($data, $this->refinalised($data, $scan['finalised'])) !== $scan['fingerprint']) {
                $this->refuse('import.refused_changed');
            }

            $batch = new CostRecordBatch($this->context, $this->trail, (int) $organisation->getKey());

            foreach ($scan['rows'] as $row) {
                $this->apply($organisation, $data, $row, $batch);
            }

            $batch->flush();

            return ['totals' => $scan['totals']];
        }));
    }

    /**
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>, creates: array<string, list<string>>, rows: list<array{cells: array<string, string>, fields: array<string, ?string>, figures: ?array<string, ?int>, year: ?int}>, finalised: array<int, string|false>, fingerprint: string}
     */
    private function scan(Organisation $organisation, string $path): array
    {
        $data = $this->load($organisation);
        $yearModels = $data['yearModels'];
        $years = $data['years'];
        $records = $data['records'];
        $owners = $data['owners'];
        $applications = $data['applications'];

        $rows = [];
        $lines = [];
        $seenYears = [];
        $seenFields = [];
        $finalised = [];
        $creates = ['owners' => [], 'applications' => []];
        $totals = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];

        foreach ((new CsvReader($path))->lines() as $number => $cells) {
            if ($number === 1) {
                continue;
            }

            if (count($cells) > count(self::COLUMNS)) {
                $lines[] = ['line' => $number, 'status' => 'error', 'messages' => [(string) __('csvx.too_many_cells', ['line' => $number, 'count' => count($cells), 'expected' => count(self::COLUMNS)])]];
                $totals['errors']++;

                continue;
            }

            $row = array_combine(self::COLUMNS, array_pad(array_map('trim', $cells), count(self::COLUMNS), ''));
            [$messages, $figures, $fields] = (new CostRecordRowValidator)->validate($number, $row, $years);
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

            $record = $records->get($code);
            $year = $yearModels->get((int) $row['jaar']);

            if ($messages === [] && $figures !== null && $year !== null && $this->figuresChange($record, $figures, $year->getKey())) {
                $canvas = $finalised[$year->year] ??= $this->finalised->finalisedInOrganisation($year) ?? false;
                $canvas !== false && $messages[] = __('import.year_finalised', ['line' => $number, 'year' => $year->year, 'canvas' => $canvas]);
            }

            if ($messages === []) {
                $this->collect($creates['owners'], $row['eigenaar'], $owners);
                $this->collect($creates['applications'], $row['applicatie'], $applications);
            }

            $status = $messages !== [] ? 'error' : $this->status($record, $fields, $figures, $years[(int) $row['jaar']] ?? null, $owners, $applications);
            $totals[$status === 'error' ? 'errors' : $status]++;
            $lines[] = ['line' => $number, 'status' => $status, 'messages' => $messages];
            $messages === [] && $rows[] = ['cells' => $row, 'fields' => $fields, 'figures' => $figures, 'year' => $years[(int) $row['jaar']] ?? null];
        }

        return [
            'lines' => $lines,
            'totals' => $totals,
            'creates' => ['owners' => array_values($creates['owners']), 'applications' => array_values($creates['applications'])],
            'rows' => $rows,
            'finalised' => $finalised,
            'fingerprint' => $this->fingerprint($data, $finalised),
        ];
    }

    /**
     * @return array{yearModels: Collection<int, FiscalYear>, years: array<int, int>, records: Collection<string, CostRecord>, owners: array<int, string>, applications: array<int, string>}
     */
    private function load(Organisation $organisation): array
    {
        /** @var Collection<int, FiscalYear> $yearModels */
        $yearModels = FiscalYear::where('organisation_id', $organisation->getKey())->get()->keyBy('year');
        /** @var Collection<string, CostRecord> $records */
        $records = CostRecord::with('yearlyFigures')->where('organisation_id', $organisation->getKey())->get()->keyBy('code_normalised');

        return [
            'yearModels' => $yearModels,
            'years' => $yearModels->map->getKey()->all(),
            'records' => $records,
            'owners' => $organisation->owners()->pluck('name_normalised', 'id')->all(),
            'applications' => $organisation->applications()->pluck('name_normalised', 'id')->all(),
        ];
    }

    /**
     * @param  array{yearModels: Collection<int, FiscalYear>, years: array<int, int>, records: Collection<string, CostRecord>, owners: array<int, string>, applications: array<int, string>}  $data
     * @param  array<int, string|false>  $finalised
     */
    private function fingerprint(array $data, array $finalised): string
    {
        $records = $data['records']
            ->map(fn (CostRecord $record) => [
                $record->only(['code_normalised', 'name', 'cost_kind', 'supplier', 'owner_id', 'cost_centre', 'application_id']),
                $record->yearlyFigures->map->only(['fiscal_year_id', 'budget_cents', 'forecast_cents', 'run_bp', 'change_bp', 'fte_hundredths'])->sortBy('fiscal_year_id')->values()->all(),
            ])
            ->sortKeys()
            ->all();

        return md5(serialize([$data['years'], $records, $data['owners'], $data['applications'], $finalised]));
    }

    /**
     * @param  array{yearModels: Collection<int, FiscalYear>, years: array<int, int>, records: Collection<string, CostRecord>, owners: array<int, string>, applications: array<int, string>}  $data
     * @param  array<int, string|false>  $finalised
     * @return array<int, string|false|null>
     */
    private function refinalised(array $data, array $finalised): array
    {
        $again = [];

        foreach (array_keys($finalised) as $year) {
            $model = $data['yearModels']->get($year);
            $again[$year] = $model instanceof FiscalYear ? $this->finalised->finalisedInOrganisation($model) ?? false : null;
        }

        return $again;
    }

    /**
     * @param  array{yearModels: Collection<int, FiscalYear>, years: array<int, int>, records: Collection<string, CostRecord>, owners: array<int, string>, applications: array<int, string>}  $data
     * @param  array{cells: array<string, string>, fields: array<string, ?string>, figures: ?array<string, ?int>, year: ?int}  $row
     */
    private function apply(Organisation $organisation, array &$data, array $row, CostRecordBatch $batch): void
    {
        $attributes = [
            'name' => $row['fields']['name'],
            'cost_kind' => $row['fields']['kind'],
            'supplier' => $row['fields']['supplier'],
            'cost_centre' => $row['fields']['cost_centre'],
            'owner_id' => $this->entry(Owner::class, $organisation, $data['owners'], $row['cells']['eigenaar']),
            'application_id' => $this->entry(Application::class, $organisation, $data['applications'], $row['cells']['applicatie']),
        ];

        $key = mb_strtolower($row['cells']['code']);
        $record = $data['records']->get($key);

        if ($record instanceof CostRecord) {
            $record->fill($attributes)->save();
        } else {
            $batch->has($key) || $batch->record($key, $row['cells']['code'], $attributes);

            if ($row['figures'] !== null && $row['year'] !== null) {
                $batch->figure($key, $row['year'], $row['figures']);
            }

            return;
        }

        if ($row['figures'] === null || $row['year'] === null) {
            return;
        }

        $figure = $record->yearlyFigures->firstWhere('fiscal_year_id', $row['year']);

        if ($figure instanceof YearlyFigure) {
            $figure->fill($row['figures'])->save();

            return;
        }

        $record->yearlyFigures->push(YearlyFigure::create(['cost_record_id' => $record->getKey(), 'fiscal_year_id' => $row['year'], ...$row['figures']]));
    }

    /**
     * @param  class-string<Application|Owner>  $class
     * @param  array<int, string>  $names
     */
    private function entry(string $class, Organisation $organisation, array &$names, string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        $normalised = mb_strtolower($name);
        $id = array_search($normalised, $names, true);

        if ($id === false) {
            $id = (int) $class::create(['organisation_id' => $organisation->getKey(), 'name' => $name])->getKey();
            $names[$id] = $normalised;
        }

        return (int) $id;
    }

    private function refuse(string $key): never
    {
        throw ValidationException::withMessages(['file' => (string) __($key)]);
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

        return $this->figuresChange($record, $figures, $yearId) ? 'updated' : 'unchanged';
    }

    /**
     * @param  array<string, ?int>  $figures
     */
    private function figuresChange(?CostRecord $record, array $figures, ?int $yearId): bool
    {
        $figure = $record?->yearlyFigures->firstWhere('fiscal_year_id', $yearId);

        return ! $figure instanceof YearlyFigure || $figure->only(array_keys($figures)) !== $figures;
    }

    /**
     * @param  array<string, string>  $names
     * @param  array<int, string>  $existing
     */
    private function collect(array &$names, string $name, array $existing): void
    {
        $key = mb_strtolower($name);

        if ($name !== '' && ! in_array($key, $existing, true)) {
            $names[$key] ??= $name;
        }
    }
}
