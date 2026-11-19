<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Models\Element;
use App\Services\Allocation\Redistribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedistributionController extends Controller
{
    public function __construct(private readonly Redistribution $redistribution) {}

    public function preview(Canvas $canvas, Element $element): JsonResponse
    {
        abort_unless($element->canvas_id === $canvas->id, 404);

        return response()->json($this->redistribution->preview($element));
    }

    public function apply(Request $request, Canvas $canvas, Element $element): RedirectResponse
    {
        abort_unless($element->canvas_id === $canvas->id, 404);

        $request->validate([
            'choices' => ['array'],
            'choices.*.mode' => ['required', 'in:move,unknown'],
            'choices.*.target' => ['nullable', 'string'],
        ]);

        /** @var array<array-key, array{mode?: string, target?: string|null}> $choices */
        $choices = $request->input('choices', []);

        $this->redistribution->apply($element, $choices);

        return back();
    }
}
