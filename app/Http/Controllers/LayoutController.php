<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetLayoutRequest;
use App\Http\Requests\SaveLayoutRequest;
use App\Models\Canvas;
use App\Services\Layout\LayoutStore;
use Illuminate\Http\JsonResponse;

class LayoutController extends Controller
{
    public function __construct(private readonly LayoutStore $store) {}

    public function update(SaveLayoutRequest $request, Canvas $canvas): JsonResponse
    {
        return response()->json(['layout_version' => $this->store->save($canvas, $request->validated())]);
    }

    public function reset(ResetLayoutRequest $request, Canvas $canvas): JsonResponse
    {
        return response()->json(['layout_version' => $this->store->reset($canvas, $request->string('scope')->toString(), $request->input('element_id'), $request->safe()->only(['structure_version', 'layout_version']))]);
    }
}
