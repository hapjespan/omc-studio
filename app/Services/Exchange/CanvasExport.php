<?php

namespace App\Services\Exchange;

use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\Modality;
use App\Models\Organisation;
use App\Models\Statement;
use App\Models\YearlyFigure;
use App\Services\Layout\LayoutSnapshot;
use Illuminate\Support\Str;

class CanvasExport
{
    public const FORMAT = 'omc-studio-canvas';

    public function __construct(private readonly LayoutSnapshot $layouts, private readonly ConsistentRead $consistent) {}

    /** @return array<string, mixed> */
    public function build(Canvas $canvas): array
    {
        return $this->consistent->run(fn () => $this->read($canvas));
    }

    /** @return array<string, mixed> */
    private function read(Canvas $canvas): array
    {
        $canvas->refresh();
        $organisation = $canvas->organisation()->firstOrFail();
        $canvas->setRelation('organisation', $organisation);
        $years = $this->years($organisation);

        return [
            'format' => self::FORMAT,
            'format_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'organisation' => $this->organisation($organisation, $years),
            'cost_records' => $this->costRecords($organisation, $years),
            'canvas' => $this->canvasBlock($canvas, $years),
        ];
    }

    /** @return array<int, int> */
    public function years(Organisation $organisation): array
    {
        return $organisation->fiscalYears()->orderBy('year')->pluck('year', 'id')->all();
    }

    /**
     * @param  array<int, int>  $years
     * @return array<string, mixed>
     */
    public function canvasBlock(Canvas $canvas, array $years): array
    {
        return [
            'name' => $canvas->name,
            'statements' => $this->statements($canvas),
            'elements' => $this->elements($canvas),
            'layout' => $this->layout($canvas),
            'allocation_sets' => $this->sets($canvas, $years),
            'finalised_years' => FinalisedYear::where('canvas_id', $canvas->getKey())->get()
                ->map(fn (FinalisedYear $year) => ['year' => $years[$year->fiscal_year_id], 'finalised_at' => $year->finalised_at->toIso8601String()])
                ->sortBy('year')->values()->all(),
        ];
    }

    /** @return array{name: string, statements: list<array<string, mixed>>, elements: list<array<string, mixed>>} */
    public function templateBlock(Canvas $template): array
    {
        return ['name' => $template->name, 'statements' => $this->statements($template), 'elements' => $this->elements($template)];
    }

    public function fileName(Canvas $canvas): string
    {
        return $canvas->organisation?->slug.'-'.Str::slug($canvas->name).'-'.now()->format('Y-m-d').'.json';
    }

    /**
     * @param  array<int, int>  $years
     * @return array<string, mixed>
     */
    public function organisation(Organisation $organisation, array $years): array
    {
        return [
            'name' => $organisation->name,
            'owners' => $organisation->owners()->orderBy('name')->pluck('name')->all(),
            'applications' => $organisation->applications()->orderBy('name')->get()->map->only(['name', 'supplier'])->all(),
            'fiscal_years' => array_values($years),
        ];
    }

    /**
     * @param  array<int, int>  $years
     * @return list<array<string, mixed>>
     */
    public function costRecords(Organisation $organisation, array $years): array
    {
        return CostRecord::with(['owner', 'application', 'yearlyFigures'])->where('organisation_id', $organisation->getKey())->orderBy('code')->orderBy('id')->get()
            ->map(fn (CostRecord $record) => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'cost_kind' => $record->cost_kind->value,
                'supplier' => $record->supplier,
                'owner' => $record->owner->name,
                'cost_centre' => $record->cost_centre,
                'application' => $record->application?->name,
                'description' => $record->description,
                'archived_at' => $record->archived_at?->toIso8601String(),
                'yearly_figures' => $record->yearlyFigures->sortBy(fn (YearlyFigure $figure) => $years[$figure->fiscal_year_id])->map(fn (YearlyFigure $figure) => [
                    'year' => $years[$figure->fiscal_year_id],
                    ...$figure->only(['budget_cents', 'forecast_cents', 'run_bp', 'change_bp', 'fte_hundredths']),
                    'reference_date' => $figure->reference_date?->format('Y-m-d'),
                    'remark' => $figure->remark,
                ])->values()->all(),
            ])->all();
    }

    /** @return ?array{areas: array<string, array<string, mixed>>, elements: array<string, array<string, mixed>>} */
    private function layout(Canvas $canvas): ?array
    {
        $stored = $this->layouts->for($canvas);

        if ($stored === []) {
            return null;
        }

        $areas = array_map(fn (array $box) => array_diff_key($box, ['mode' => true]), array_intersect_key($stored, array_flip(Area::KEYS)));
        $elements = [];

        foreach (Element::where('canvas_id', $canvas->getKey())->whereNull('archived_at')->orderBy('position')->orderBy('name')->pluck('id') as $id) {
            $elements[$id] = $stored[$id] ?? ['mode' => 'automatic'];
        }

        return ['areas' => $areas, 'elements' => $elements];
    }

    /** @return list<array<string, mixed>> */
    private function statements(Canvas $canvas): array
    {
        return Statement::where('canvas_id', $canvas->getKey())->orderBy('kind')->orderBy('position')->get()
            ->map(fn (Statement $statement) => [...$statement->only(['title', 'body', 'position']), 'kind' => $statement->kind->value])->all();
    }

    /** @return list<array<string, mixed>> */
    private function elements(Canvas $canvas): array
    {
        $modalities = Modality::pluck('key', 'id')->all();

        return Element::without('canvas')->with('area')->where('canvas_id', $canvas->getKey())->orderBy('position')->orderBy('name')->get()
            ->map(fn (Element $element) => [
                'id' => $element->id,
                'parent_id' => $element->parent_id,
                'area' => $element->area->key,
                'kind' => $element->kind->value,
                'name' => $element->name,
                'description' => $element->description,
                'modality' => $element->modality_id === null ? null : $modalities[$element->modality_id],
                'rationale' => $element->rationale,
                'icon' => $element->icon,
                'position' => $element->position,
                'is_unknown_allocation' => $element->is_unknown_allocation,
                'archived_at' => $element->archived_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @param  array<int, int>  $years
     * @return list<array<string, mixed>>
     */
    private function sets(Canvas $canvas, array $years): array
    {
        return AllocationSet::with('lines')->where('canvas_id', $canvas->getKey())->orderBy('id')->get()
            ->map(fn (AllocationSet $set) => [
                'cost_record_id' => $set->cost_record_id,
                'year' => $years[$set->fiscal_year_id],
                'lines' => $set->lines->map(fn ($line) => ['element_id' => $line->element_id, 'basis_points' => $line->basis_points])->all(),
            ])->all();
    }
}
