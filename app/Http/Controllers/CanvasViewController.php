<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Models\Statement;
use Inertia\Inertia;
use Inertia\Response;

class CanvasViewController extends Controller
{
    public function __invoke(Canvas $canvas): Response
    {
        $modalities = Modality::orderBy('position')->get();
        $letters = $modalities->pluck('letter', 'id');

        return Inertia::render('Canvas/View', [
            'canvas' => ['id' => $canvas->id, 'name' => $canvas->name, 'archived' => $canvas->archived_at !== null],
            'canWrite' => false,
            'layout' => [
                'areas' => Area::orderBy('position')->get()->map(fn (Area $area) => [
                    'key' => $area->key,
                    'kind' => $area->kind,
                    'position' => $area->position,
                    'label' => __('areas.'.$area->key.'.name'),
                ])->values(),
                'directionLabels' => ['mission' => __('overview.mission'), 'vision' => __('overview.vision'), 'goals' => __('view.direction.goals')],
            ],
            'elements' => Element::where('canvas_id', $canvas->id)->whereNull('archived_at')->with('area:id,key')->get()->map(fn (Element $element) => [
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
            'statements' => Statement::where('canvas_id', $canvas->id)->orderBy('position')->get()->map(fn (Statement $statement) => [
                'kind' => $statement->kind->value,
                'title' => $statement->title,
                'body' => $statement->body,
                'position' => $statement->position,
            ])->values(),
            'modalities' => $modalities->map(fn (Modality $modality) => ['key' => $modality->key, 'letter' => $modality->letter, 'colour' => $modality->colour])->values(),
        ]);
    }
}
