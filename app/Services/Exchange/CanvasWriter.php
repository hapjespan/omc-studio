<?php

namespace App\Services\Exchange;

use App\Enums\CanvasKind;
use App\Models\Application;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Layout;
use App\Models\Modality;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\Statement;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use App\Services\Canvas\OrganisationService;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Support\NameNormaliser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CanvasWriter
{
    private const CHUNK = 400;

    public function __construct(
        private readonly OrganisationService $organisations,
        private readonly AuditContext $context,
        private readonly AuditTrail $trail,
    ) {}

    public function organisation(string $name): Organisation
    {
        return $this->organisations->create($name);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{array<int, int>, array<string, int>, array<string, int>}
     */
    public function prepare(int $organisationId, array $data): array
    {
        $years = $this->fiscalYears($organisationId, $data['organisation']['fiscal_years']);
        $owners = Owner::where('organisation_id', $organisationId)->pluck('id', 'name_normalised')->all();
        $applications = Application::where('organisation_id', $organisationId)->pluck('id', 'name_normalised')->all();

        foreach ($data['organisation']['owners'] as $name) {
            $this->entry(Owner::class, $organisationId, $owners, $name);
        }

        foreach ($data['organisation']['applications'] as $application) {
            $this->entry(Application::class, $organisationId, $applications, $application['name'], ['supplier' => $application['supplier'] ?? null]);
        }

        return [$years, $owners, $applications];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $reused
     * @param  array<int, int>  $years
     * @param  array<string, int>  $owners
     * @param  array<string, int>  $applications
     * @return array<string, string>
     */
    public function costRecords(int $organisationId, array $rows, array $reused, array $years, array &$owners, array &$applications, bool $keepIds = false): array
    {
        $batch = new CostRecordBatch($this->context, $this->trail, $organisationId, $keepIds);
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
     * @param  array<string, mixed>  $block
     * @param  array<string, string>  $records
     * @param  array<int, int>  $years
     */
    public function canvas(int $organisationId, string $name, array $block, array $records, array $years): Canvas
    {
        $canvas = Canvas::create(['organisation_id' => $organisationId, 'kind' => CanvasKind::Canvas, 'name' => trim($name)]);

        $this->statements($canvas, $block['statements']);
        $elements = $this->elements($canvas, $block['elements']);

        $this->layout((int) $canvas->getKey(), $block['layout'] ?? null, $elements);

        $this->sets((int) $canvas->getKey(), $block['allocation_sets'], $records, $elements, $years);

        foreach ($block['finalised_years'] as $finalised) {
            FinalisedYear::create([
                'canvas_id' => $canvas->getKey(),
                'fiscal_year_id' => $years[$finalised['year']],
                'finalised_at' => Carbon::parse($finalised['finalised_at'])->utc(),
                'finalised_by' => null,
            ]);
        }

        return $canvas;
    }

    /**
     * @param  ?array<string, mixed>  $layout
     * @param  array<string, string>  $elements
     */
    private function layout(int $canvasId, ?array $layout, array $elements): void
    {
        $areas = Area::pluck('id', 'key')->all();
        $rows = [];

        foreach ($layout['areas'] ?? [] as $key => $box) {
            $rows[] = $this->layoutRow($canvasId, ['area_id' => $areas[$key]], $box);
        }

        foreach ($layout['elements'] ?? [] as $id => $box) {
            if (($box['mode'] ?? null) === 'manual') {
                $rows[] = $this->layoutRow($canvasId, ['element_id' => $elements[$id]], $box);
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            Layout::query()->insert($chunk);
        }
    }

    /**
     * @param  array<string, string|int>  $owner
     * @param  array<string, mixed>  $box
     * @return array<string, mixed>
     */
    private function layoutRow(int $canvasId, array $owner, array $box): array
    {
        return [
            'canvas_id' => $canvasId,
            'area_id' => null,
            'element_id' => null,
            ...$owner,
            'x' => $box['x'],
            'y' => $box['y'],
            'width' => $box['width'],
            'height' => $box['height'],
            'z' => $box['z'] ?? 0,
            'style' => json_encode($box['style'] ?? [], JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT),
            'format_version' => 1,
            'updated_at' => now(),
        ];
    }

    /** @param array{statements: list<array<string, mixed>>, elements: list<array<string, mixed>>} $block */
    public function template(array $block, string $name): Canvas
    {
        $template = Canvas::create(['organisation_id' => null, 'kind' => CanvasKind::Template, 'name' => $name]);

        $this->statements($template, $block['statements']);
        $this->elements($template, $block['elements']);

        return $template;
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

        $key = NameNormaliser::normalise($name);
        $names[$key] ??= (int) $class::create(['organisation_id' => $organisationId, 'name' => $name, ...$extra])->getKey();

        return (int) $names[$key];
    }

    /** @param list<array<string, mixed>> $statements */
    private function statements(Canvas $canvas, array $statements): void
    {
        foreach ($statements as $statement) {
            Statement::create(['canvas_id' => $canvas->getKey(), ...array_intersect_key($statement, array_flip(['kind', 'title', 'body', 'position']))]);
        }
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
                'name_normalised' => NameNormaliser::normalise($row['name']),
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
        return $value === null ? null : Carbon::parse((string) $value)->utc()->toDateTimeString();
    }
}
