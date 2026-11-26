<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Http\Requests\CanvasRequest;
use App\Models\Canvas;
use App\Services\Canvas\TemplateService;
use Illuminate\Http\RedirectResponse;

class TemplateController extends Controller
{
    public function __construct(private readonly TemplateService $templates) {}

    public function update(CanvasRequest $request, Canvas $canvas): RedirectResponse
    {
        $this->templates->rename($this->template($canvas), $request->string('name')->toString());

        return back()->with('status', __('templates.renamed'));
    }

    public function destroy(CanvasRequest $request, Canvas $canvas): RedirectResponse
    {
        $this->templates->delete($this->template($canvas), $request->string('confirmation')->toString());

        return redirect('/overview')->with('status', __('templates.deleted'));
    }

    private function template(Canvas $canvas): Canvas
    {
        abort_unless($canvas->kind === CanvasKind::Template, 404);

        return $canvas;
    }
}
