<?php

namespace App\Http\Controllers;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Statement;
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
            'selected' => $areas->firstWhere('kind', 'activities')['key'] ?? $areas->first()['key'],
        ]);
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
