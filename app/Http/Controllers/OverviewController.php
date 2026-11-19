<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Enums\Role;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Models\Organisation;
use App\Models\Statement;
use App\Services\Canvas\CanvasLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __construct(private readonly CanvasLifecycle $lifecycle) {}

    public function __invoke(Request $request): Response
    {
        $showArchived = $request->boolean('archived');
        $canWrite = $request->user()?->role === Role::Administrator;

        $organisations = Organisation::whereNull('archived_at')
            ->with(['canvases' => fn ($query) => $query
                ->where('kind', CanvasKind::Canvas->value)
                ->when(! $showArchived, fn ($query) => $query->whereNull('archived_at'))
                ->orderBy('name_normalised')])
            ->orderBy('name_normalised')
            ->get();

        $canvasIds = $organisations->flatMap->canvases->pluck('id');
        $elements = Element::whereIn('canvas_id', $canvasIds)->whereNull('archived_at')->with('area:id,key')->get()->toBase()->groupBy('canvas_id');
        $letters = Modality::pluck('letter', 'id');
        $statements = Statement::whereIn('canvas_id', $canvasIds)->orderBy('position')->get()->toBase()->groupBy('canvas_id');

        return Inertia::render('Overview', [
            'organisations' => $organisations->map(fn (Organisation $organisation) => $this->section($organisation, $elements, $statements, $letters, $canWrite))->values(),
            'layout' => [
                'areas' => Area::orderBy('position')->get()->map(fn (Area $area) => [
                    'key' => $area->key,
                    'kind' => $area->kind,
                    'position' => $area->position,
                    'label' => __('areas.'.$area->key.'.name'),
                ])->values(),
                'directionLabels' => ['mission' => __('overview.mission'), 'vision' => __('overview.vision'), 'goals' => __('overview.goals')],
            ],
            'showArchived' => $showArchived,
            'canWrite' => $canWrite,
            'templates' => $canWrite
                ? Canvas::where('kind', CanvasKind::Template->value)->orderByDesc('is_blank_template')->orderBy('name_normalised')->get(['id', 'name', 'is_blank_template'])
                : [],
        ]);
    }

    /**
     * @param  Collection<int|string, Collection<int, Element>>  $elements
     * @param  Collection<int|string, Collection<int, Statement>>  $statements
     * @param  Collection<int|string, string>  $letters
     * @return array<string, mixed>
     */
    private function section(Organisation $organisation, Collection $elements, Collection $statements, Collection $letters, bool $canWrite): array
    {
        return [
            'id' => $organisation->id,
            'name' => $organisation->name,
            'canvases' => $organisation->canvases->map(fn (Canvas $canvas) => [
                'id' => $canvas->id,
                'name' => $canvas->name,
                'updated_at' => $canvas->updated_at?->toIso8601String(),
                'archived' => $canvas->archived_at !== null,
                'counts' => $canWrite ? $this->lifecycle->deletionCounts($canvas) : null,
                'elements' => ($elements[$canvas->id] ?? collect())->map(fn (Element $element) => [
                    'id' => $element->id,
                    'area' => $element->area->key,
                    'parent' => $element->parent_id,
                    'kind' => $element->kind->value,
                    'name' => $element->name,
                    'position' => $element->position,
                    'modality' => $letters[$element->modality_id] ?? null,
                    'unknownAllocation' => $element->is_unknown_allocation,
                ])->values(),
                'statements' => ($statements[$canvas->id] ?? collect())->map(fn (Statement $statement) => ['kind' => $statement->kind->value, 'title' => $statement->title, 'body' => $statement->body, 'position' => $statement->position])->values(),
            ])->values(),
        ];
    }
}
