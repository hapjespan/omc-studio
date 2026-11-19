<?php

namespace App\Services\Exchange;

use App\Enums\CanvasKind;
use App\Models\Application;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Modality;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\Statement;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use App\Services\Canvas\OrganisationService;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CanvasImport
{
    use WritableGuard;

    private const CHUNK = 400;

    public function __construct(
        private readonly CanvasImportValidator $validator,
        private readonly OrganisationService $organisations,
        private readonly SerialisedWrite $writes,
        private readonly AuditContext $context,
        private readonly AuditTrail $trail,
    ) {}

    /**
     * @return array{errors: list<array{location: string, message: string}>, count: int, totals: array<string, int>}
     */
    public function preview(string $content, ?Organisation $target, string $canvasName, ?string $organisationName): array
    {
        $result = $this->check($content, $target, $canvasName, $organisationName);

        return ['errors' => $result['errors'], 'count' => $result['count'], 'totals' => $this->totals($result['data'], $result['reused'])];
    }

    /**
     * @return array{canvas_id: int, organisation_id: int, totals: array<string, int>}
     */
    public function commit(string $content, ?Organisation $target, string $canvasName, ?string $organisationName): array
    {
        $result = $this->check($content, $target, $canvasName, $organisationName);

        if ($result['errors'] !== []) {
            $this->refuseFile('import.refused_errors');
        }

        $data = $result['data'];

        return $this->writes->run($target === null ? [] : [$target], function () use ($target, $data, $canvasName, $organisationName) {
            if ($target !== null) {
                $this->assertWritable($target);
            }

            return $this->context->during('imported', function () use ($target, $data, $canvasName, $organisationName) {
                $found = $this->validator->conflicts($data, $target, $canvasName, $organisationName);

                if ($found['errors'] !== []) {
                    $this->refuseFile('import.refused_changed');
                }

                return $this->build($target ?? $this->organisations->create((string) $organisationName), $data, $canvasName, $found['reused']);
            });
        });
    }

    /**
     * @return array{data: array<string, mixed>, errors: list<array{location: string, message: string}>, count: int, reused: list<string>}
     */
    private function check(string $content, ?Organisation $target, string $canvasName, ?string $organisationName): array
    {
        $result = $this->validator->check($content, $target, $canvasName, $organisationName);

        if ($result['data'] === null) {
            $this->refuseFile('jsonx.refused_file');
        }

        return ['data' => $result['data'], 'errors' => $result['errors'], 'count' => $result['count'], 'reused' => $result['reused']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $reused
     * @return array{canvas_id: int, organisation_id: int, totals: array<string, int>}
     */
    private function build(Organisation $organisation, array $data, string $canvasName, array $reused): array
    {
        $organisationId = (int) $organisation->getKey();
        $years = $this->fiscalYears($organisationId, $data['organisation']['fiscal_years']);
        $owners = Owner::where('organisation_id', $organisationId)->pluck('id', 'name_normalised')->all();
        $applications = Application::where('organisation_id', $organisationId)->pluck('id', 'name_normalised')->all();

        foreach ($data['organisation']['owners'] as $name) {
            $this->entry(Owner::class, $organisationId, $owners, $name);
        }

        foreach ($data['organisation']['applications'] as $application) {
            $this->entry(Application::class, $organisationId, $applications, $application['name'], ['supplier' => $application['supplier'] ?? null]);
        }

        $canvas = Canvas::create(['organisation_id' => $organisationId, 'kind' => CanvasKind::Canvas, 'name' => trim($canvasName)]);

        foreach ($data['canvas']['statements'] as $statement) {
            Statement::create(['canvas_id' => $canvas->getKey(), ...array_intersect_key($statement, array_flip(['kind', 'title', 'body', 'position']))]);
        }

        $elements = $this->elements($canvas, $data['canvas']['elements']);
        $records = $this->costRecords($organisationId, $data['cost_records'], $reused, $years, $owners, $applications);

        $this->sets((int) $canvas->getKey(), $data['canvas']['allocation_sets'], $records, $elements, $years);

        foreach ($data['canvas']['finalised_years'] as $finalised) {
            FinalisedYear::create([
                'canvas_id' => $canvas->getKey(),
                'fiscal_year_id' => $years[$finalised['year']],
                'finalised_at' => Carbon::parse($finalised['finalised_at']),
                'finalised_by' => null,
            ]);
        }

        return ['canvas_id' => (int) $canvas->getKey(), 'organisation_id' => $organisationId, 'totals' => $this->totals($data, $reused)];
    }

    /**
     * @param  list<int>  $years
     * @return array<int, int>
     */
    private function fiscalYears(int $organisationId, array $years): array
    {
        $known = FiscalYear::where('organisation_id', $organisationId)->pluck('id', 'year')->all();

        foreach ($years as $year) {
            $known[$year] ??= (int) FiscalYear::create(['organisation_id' => $organisationId, 'year' => $year])->getKey();
        }

        return $known;
    }

    /**
     * @param  class-string<Application|Owner>  $class
     * @param  array<string, int>  $names
     * @param  array<string, mixed>  $extra
     */
    private function entry(string $class, int $organisationId, array &$names, ?string $name, array $extra = []): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $key = mb_strtolower(trim($name));
        $names[$key] ??= (int) $class::create(['organisation_id' => $organisationId, 'name' => $name, ...$extra])->getKey();

        return (int) $names[$key];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function elements(Canvas $canvas, array $rows): array
    {
        $areas = Area::pluck('id', 'key')->all();
        $modalities = Modality::pluck('id', 'key')->all();
        $unknownId = (string) UnknownAllocationDomain::ensure($canvas)?->getKey();
        $map = [];
        $parents = [];

        foreach ($rows as $row) {
            $map[$row['id']] = $row['is_unknown_allocation'] === true ? $unknownId : (string) Str::uuid();
            $parents[$row['id']] = $row['parent_id'];
        }

        $depths = [];
        $levels = [];

        foreach ($rows as $row) {
            if ($row['is_unknown_allocation'] === true) {
                continue;
            }

            $levels[$this->depth($row['id'], $parents, $depths)][] = [
                'id' => $map[$row['id']],
                'canvas_id' => $canvas->getKey(),
                'area_id' => $areas[$row['area']],
                'parent_id' => $row['parent_id'] === null ? null : $map[$row['parent_id']],
                'kind' => $row['kind'],
                'name' => $row['name'],
                'name_normalised' => mb_strtolower(trim($row['name'])),
                'description' => $row['description'] ?? null,
                'modality_id' => ($row['modality'] ?? null) === null ? null : $modalities[$row['modality']],
                'rationale' => $row['rationale'] ?? null,
                'icon' => $row['icon'] ?? null,
                'position' => $row['position'],
                'is_unknown_allocation' => false,
                'archived_at' => $this->moment($row['archived_at'] ?? null),
            ];
        }

        ksort($levels);

        foreach ($levels as $level) {
            foreach (array_chunk($level, self::CHUNK) as $chunk) {
                $this->insert('elements', $chunk, true);
                $this->trail->recordMany('element', $this->context->action('created'), null, array_map(fn (array $row) => [$row['id'], (string) $row['name'], $this->changes($row)], $chunk), (int) $canvas->getKey());
            }
        }

        return $map;
    }

    /**
     * @param  array<string, ?string>  $parents
     * @param  array<string, int>  $depths
     */
    private function depth(string $id, array $parents, array &$depths): int
    {
        $parent = $parents[$id] ?? null;

        return $depths[$id] ??= $parent === null ? 0 : $this->depth($parent, $parents, $depths) + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $reused
     * @param  array<int, int>  $years
     * @param  array<string, int>  $owners
     * @param  array<string, int>  $applications
     * @return array<string, string>
     */
    private function costRecords(int $organisationId, array $rows, array $reused, array $years, array &$owners, array &$applications): array
    {
        $batch = new CostRecordBatch($this->context, $this->trail, $organisationId);
        $keep = array_flip($reused);
        $map = [];

        foreach ($rows as $row) {
            if (isset($keep[$row['id']])) {
                $map[$row['id']] = $row['id'];

                continue;
            }

            $batch->record($row['id'], $row['code'] ?? null, [
                'name' => $row['name'],
                'cost_kind' => $row['cost_kind'],
                'supplier' => $row['supplier'] ?? null,
                'cost_centre' => $row['cost_centre'] ?? null,
                'owner_id' => $this->entry(Owner::class, $organisationId, $owners, $row['owner']),
                'application_id' => $this->entry(Application::class, $organisationId, $applications, $row['application'] ?? null),
                'description' => $row['description'] ?? null,
                'archived_at' => $this->moment($row['archived_at'] ?? null),
            ]);

            $map[$row['id']] = $batch->idOf($row['id']);

            foreach ($row['yearly_figures'] as $figure) {
                $batch->figure($row['id'], $years[$figure['year']], [
                    'budget_cents' => $figure['budget_cents'],
                    'forecast_cents' => $figure['forecast_cents'] ?? null,
                    'run_bp' => $figure['run_bp'] ?? null,
                    'change_bp' => $figure['change_bp'] ?? null,
                    'fte_hundredths' => $figure['fte_hundredths'] ?? null,
                    'reference_date' => $figure['reference_date'] ?? null,
                    'remark' => $figure['remark'] ?? null,
                ]);
            }
        }

        $batch->flush();

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $records
     * @param  array<string, string>  $elements
     * @param  array<int, int>  $years
     */
    private function sets(int $canvasId, array $rows, array $records, array $elements, array $years): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $this->insert('allocation_sets', array_map(fn (array $set) => [
                'canvas_id' => $canvasId,
                'cost_record_id' => $records[$set['cost_record_id']],
                'fiscal_year_id' => $years[$set['year']],
            ], $chunk), true);
        }

        $ids = DB::table('allocation_sets')->where('canvas_id', $canvasId)->get(['id', 'cost_record_id', 'fiscal_year_id'])
            ->mapWithKeys(fn (object $row) => [$row->cost_record_id.'|'.$row->fiscal_year_id => (int) $row->id])->all();

        $lines = [];
        $entries = [];

        foreach ($rows as $set) {
            $attributes = ['canvas_id' => $canvasId, 'cost_record_id' => $records[$set['cost_record_id']], 'fiscal_year_id' => $years[$set['year']]];
            $id = $ids[$attributes['cost_record_id'].'|'.$attributes['fiscal_year_id']];
            $entries[] = [(string) $id, (string) $id, $this->changes($attributes)];

            foreach ($set['lines'] as $position => $line) {
                $lines[] = ['allocation_set_id' => $id, 'element_id' => $elements[$line['element_id']], 'basis_points' => $line['basis_points'], 'position' => $position];
            }
        }

        foreach (array_chunk($lines, self::CHUNK) as $chunk) {
            $this->insert('allocation_lines', $chunk, false);
        }

        foreach (array_chunk($entries, self::CHUNK) as $chunk) {
            $this->trail->recordMany('allocation_set', $this->context->action('created'), null, $chunk, $canvasId);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function insert(string $table, array $rows, bool $stamped): void
    {
        $now = now();

        DB::table($table)->insert($stamped ? array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $rows) : $rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, array{null, mixed}>
     */
    private function changes(array $row): array
    {
        return array_map(fn ($value) => [null, $value], array_diff_key($row, ['id' => null]));
    }

    private function moment(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse((string) $value)->toDateTimeString();
    }

    /**
     * @param  ?array<string, mixed>  $data
     * @param  list<string>  $reused
     * @return array<string, int>
     */
    private function totals(?array $data, array $reused): array
    {
        $canvas = is_array($data['canvas'] ?? null) ? $data['canvas'] : [];
        $elements = $this->listOf($canvas['elements'] ?? null);

        return [
            'cost_records' => count($this->listOf($data['cost_records'] ?? null)),
            'reused' => count($reused),
            'elements' => count(array_filter($elements, fn ($row) => ! is_array($row) || ($row['is_unknown_allocation'] ?? null) !== true)),
            'statements' => count($this->listOf($canvas['statements'] ?? null)),
            'allocation_sets' => count($this->listOf($canvas['allocation_sets'] ?? null)),
            'finalised_years' => count($this->listOf($canvas['finalised_years'] ?? null)),
        ];
    }

    /** @return list<mixed> */
    private function listOf(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    private function refuseFile(string $key): never
    {
        throw ValidationException::withMessages(['file' => (string) __($key)]);
    }
}
