<?php

namespace App\Http\Controllers;

use App\Enums\ElementKind;
use App\Http\Resources\AllocationPage;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Models\Statement;
use App\Services\Allocation\ElementCosts;
use App\Services\Finance\FigureService;
use App\Services\Structure\ElementFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CanvasEntryController extends Controller
{
    public function __invoke(Request $request, Canvas $canvas): Response|RedirectResponse
    {
        if (! $canvas->acceptsEntryBy($request->user())) {
            return redirect("/canvases/{$canvas->id}/view");
        }

        $year = $request->attributes->get('fiscal_year');
        $counts = Element::where('canvas_id', $canvas->id)->whereNull('archived_at')->selectRaw('area_id, kind, count(*) as total')->groupBy('area_id', 'kind')->get()
            ->groupBy('area_id');
        $areas = Area::orderBy('position')->get()->map(fn (Area $area) => [
            'key' => $area->key,
            'kind' => $area->kind,
            'label' => __('areas.'.$area->key.'.name'),
            'summary' => $this->summary($area, $canvas, $counts[$area->id] ?? collect()),
        ]);

        return Inertia::render('Canvas/Entry', [
            'areas' => $areas->values(),
            'elements' => $this->elements($canvas),
            'year' => $year,
            'targets' => app(AllocationPage::class)->targets($canvas, null),
            'costs' => (object) $this->costs($canvas, $year),
            'icons' => app(ElementFields::class)->icons(),
            'modalities' => Modality::orderBy('position')->get(['key', 'letter']),
            'selected' => $areas->firstWhere('kind', 'activities')['key'] ?? $areas->first()['key'],
        ]);
    }

    /** @return array<string, array{years: list<int>, own: bool, cents?: int|null, records?: int}> */
    private function costs(Canvas $canvas, ?int $year): array
    {
        $lines = app(ElementCosts::class)->for($canvas);
        $figures = $lines === [] || $year === null ? null : app(FigureService::class)->figures($canvas, $year);

        foreach ($lines as $id => &$line) {
            $line['cents'] = $figures === null ? null : (int) ($figures->elements[$id]['total']['budget'] ?? 0);
            $line['records'] = $figures === null ? 0 : count($figures->elements[$id]['contributions'] ?? []);
        }

        return $lines;
    }

    /** @return list<array<string, mixed>> */
    private function elements(Canvas $canvas): array
    {
        $areas = Area::pluck('key', 'id');
        $modalities = Modality::pluck('key', 'id');

        return Element::where('canvas_id', $canvas->id)->orderBy('position')->orderBy('id')->get()->map(fn (Element $element) => [
            'id' => $element->id,
            'area' => $areas[$element->area_id],
            'parent' => $element->parent_id,
            'kind' => $element->kind->value,
            'name' => $element->name,
            'description' => $element->description,
            'modality' => $modalities[$element->modality_id] ?? null,
            'rationale' => $element->rationale,
            'icon' => $element->icon,
            'position' => $element->position,
            'unknownAllocation' => $element->is_unknown_allocation,
            'archived' => $element->archived_at !== null,
        ])->all();
    }

    /** @param  Collection<int, Element>  $counts */
    private function summary(Area $area, Canvas $canvas, Collection $counts): string
    {
        $count = fn (ElementKind $kind): int => (int) ($counts->firstWhere('kind', $kind)->total ?? 0);

        return match ($area->kind) {
            'text' => trans_choice('entry.texts', Statement::where('canvas_id', $canvas->id)->count()),
            'list' => trans_choice('entry.groups', $count(ElementKind::Domain)),
            default => trans_choice('entry.domains', $count(ElementKind::Domain)).' · '.trans_choice('entry.activities', $count(ElementKind::Activity)),
        };
    }
}
