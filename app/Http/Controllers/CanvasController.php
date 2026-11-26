<?php

namespace App\Http\Controllers;

use App\Http\Requests\CanvasRequest;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\TemplateService;
use Illuminate\Http\RedirectResponse;

class CanvasController extends Controller
{
    public function __construct(private readonly CanvasLifecycle $lifecycle, private readonly TemplateService $templates) {}

    public function store(CanvasRequest $request): RedirectResponse
    {
        if ($request->input('source') === 'save_template') {
            $this->templates->saveAsTemplate(Canvas::findOrFail($request->integer('canvas_id')), $request->string('name')->toString());

            return back()->with('status', __('templates.saved'));
        }

        $organisation = Organisation::findOrFail($request->integer('organisation_id'));
        $name = $request->string('name')->toString();

        $canvas = $request->input('source') === 'copy'
            ? $this->lifecycle->copy(Canvas::findOrFail($request->integer('canvas_id')), $organisation, $name)
            : $this->lifecycle->createFromTemplate($organisation, Canvas::findOrFail($request->integer('template_id')), $name);

        return redirect('/canvases/'.$canvas->id.'/entry')->with('status', __('canvas_lifecycle.created'));
    }

    public function update(CanvasRequest $request, Canvas $canvas): RedirectResponse
    {
        $this->lifecycle->rename($canvas, $request->string('name')->toString());

        return back()->with('status', __('canvas_lifecycle.renamed'));
    }

    public function archive(Canvas $canvas): RedirectResponse
    {
        $this->lifecycle->archive($canvas);

        return back()->with('status', __('canvas_lifecycle.archived'));
    }

    public function restore(Canvas $canvas): RedirectResponse
    {
        $this->lifecycle->restore($canvas);

        return back()->with('status', __('canvas_lifecycle.restored'));
    }

    public function destroy(CanvasRequest $request, Canvas $canvas): RedirectResponse
    {
        $this->lifecycle->delete($canvas, $request->string('confirmation')->toString());

        return redirect('/overview')->with('status', __('canvas_lifecycle.deleted'));
    }
}
