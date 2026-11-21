<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Enums\Role;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Statement;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\ViewData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __construct(private readonly CanvasLifecycle $lifecycle, private readonly ViewData $data) {}

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

        $templateCards = $canWrite
            ? Canvas::where('kind', CanvasKind::Template->value)->where('is_blank_template', false)->orderBy('name_normalised')->get()
            : collect();
        $canvasIds = $organisations->flatMap->canvases->pluck('id')->merge($templateCards->pluck('id'));
        $elements = Element::whereIn('canvas_id', $canvasIds)->whereNull('archived_at')->with('area:id,key')->get()->toBase()->groupBy('canvas_id');
        $statements = Statement::whereIn('canvas_id', $canvasIds)->orderBy('position')->get()->toBase()->groupBy('canvas_id');

        return Inertia::render('Overview', [
            'organisations' => $organisations->map(fn (Organisation $organisation) => $this->section($organisation, $elements, $statements, $canWrite))->values(),
            'templateCards' => $templateCards->map(fn (Canvas $template) => $this->card($template, $elements, $statements, $canWrite))->values(),
            'layout' => $this->data->layout(),
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
     * @return array<string, mixed>
     */
    private function section(Organisation $organisation, Collection $elements, Collection $statements, bool $canWrite): array
    {
        return [
            'id' => $organisation->id,
            'name' => $organisation->name,
            'is_sample' => $organisation->is_sample,
            'canvases' => $organisation->canvases->map(fn (Canvas $canvas) => $this->card($canvas, $elements, $statements, $canWrite))->values(),
        ];
    }

    /**
     * @param  Collection<int|string, Collection<int, Element>>  $elements
     * @param  Collection<int|string, Collection<int, Statement>>  $statements
     * @return array<string, mixed>
     */
    private function card(Canvas $canvas, Collection $elements, Collection $statements, bool $canWrite): array
    {
        return [
            'id' => $canvas->id,
            'name' => $canvas->name,
            'updated_at' => $canvas->updated_at?->toIso8601String(),
            'archived' => $canvas->archived_at !== null,
            'counts' => $canWrite ? $this->lifecycle->deletionCounts($canvas) : null,
            'elements' => $this->data->elements($elements[$canvas->id] ?? [], false),
            'statements' => $this->data->statements($statements[$canvas->id] ?? []),
        ];
    }
}
