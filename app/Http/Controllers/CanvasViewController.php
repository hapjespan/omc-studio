<?php

namespace App\Http\Controllers;

use App\Calculation\Figures;
use App\Enums\CostKind;
use App\Http\Resources\CanvasFigures;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\Modality;
use App\Models\Statement;
use App\Services\Finance\FigureService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CanvasViewController extends Controller
{
    private const OVERLAYS = ['none', 'modality', 'financial'];

    public function __construct(private readonly FigureService $figures) {}

    public function __invoke(Request $request, Canvas $canvas): Response
    {
        $organisation = $canvas->organisation;
        $overlay = in_array($request->query('overlay'), self::OVERLAYS, true) ? $request->query('overlay') : 'none';
        $filters = [
            'costKind' => CostKind::tryFrom($this->text($request, 'cost_kind'))?->value,
            'ownerId' => $organisation?->owners()->whereKey($this->text($request, 'owner'))->value('id'),
            'applicationId' => $organisation?->applications()->whereKey($this->text($request, 'application'))->value('id'),
        ];
        $year = $request->attributes->get('fiscal_year');
        $financial = $overlay === 'financial';
        $asked = $financial && $year !== null;
        $figures = $asked ? $this->figures->figures($canvas, $year, $filters) : null;
        $element = $request->query('element');
        $modalities = Modality::orderBy('position')->get();
        $letters = $modalities->pluck('letter', 'id');

        return Inertia::render('Canvas/View', [
            'canWrite' => false,
            'overlay' => $overlay,
            'year' => $year,
            'filters' => $filters,
            'filterOptions' => fn () => [
                'costKinds' => array_map(fn (CostKind $kind) => $kind->value, CostKind::cases()),
                'owners' => $organisation?->owners()->orderBy('name_normalised')->get(['id', 'name'])->all() ?? [],
                'applications' => $organisation?->applications()->orderBy('name_normalised')->get(['id', 'name'])->all() ?? [],
            ],
            'figures' => $figures === null ? null : CanvasFigures::from($figures),
            'figuresMessage' => $financial && $year === null ? __('overlay.no_fiscal_years') : ($asked && $figures === null ? __('overlay.closing_check_failed') : null),
            'contributions' => Inertia::optional(fn () => $figures === null || ! is_string($element) ? [] : $this->contributions($figures, $element)),
            'layout' => fn () => [
                'areas' => Area::orderBy('position')->get()->map(fn (Area $area) => [
                    'key' => $area->key,
                    'kind' => $area->kind,
                    'position' => $area->position,
                    'label' => __('areas.'.$area->key.'.name'),
                ])->values(),
                'directionLabels' => ['mission' => __('overview.mission'), 'vision' => __('overview.vision'), 'goals' => __('overview.goals')],
            ],
            'elements' => fn () => Element::where('canvas_id', $canvas->id)->whereNull('archived_at')->with('area:id,key')->get()->map(fn (Element $element) => [
                'id' => $element->id,
                'area' => $element->area->key,
                'parent' => $element->parent_id,
                'kind' => $element->kind->value,
                'name' => $element->name,
                'position' => $element->position,
                'modality' => $letters[$element->modality_id] ?? null,
                'unknownAllocation' => $element->is_unknown_allocation,
                'description' => $element->description,
                'rationale' => $element->rationale,
            ])->values(),
            'statements' => fn () => Statement::where('canvas_id', $canvas->id)->orderBy('position')->get()->map(fn (Statement $statement) => [
                'kind' => $statement->kind->value,
                'title' => $statement->title,
                'body' => $statement->body,
                'position' => $statement->position,
            ])->values(),
            'modalities' => fn () => $modalities->map(fn (Modality $modality) => ['key' => $modality->key, 'letter' => $modality->letter, 'colour' => $modality->colour])->values(),
        ]);
    }

    private function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /** @return list<array{name: string, id: string|int, amountCents: int, basisPoints: int}> */
    private function contributions(Figures $figures, string $element): array
    {
        $rows = CanvasFigures::contributions($figures, $element);
        $names = CostRecord::whereIn('id', array_column($rows, 'id'))->pluck('name', 'id');

        return array_map(fn (array $row) => ['name' => $names[$row['id']] ?? ''] + $row, $rows);
    }
}
