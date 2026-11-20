<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickEntryRequest;
use App\Models\Area;
use App\Models\Canvas;
use App\Services\Structure\QuickEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class QuickEntryController extends Controller
{
    public function __construct(private readonly QuickEntry $quickEntry) {}

    public function preview(QuickEntryRequest $request, Canvas $canvas): JsonResponse
    {
        return response()->json($this->quickEntry->preview($canvas, $this->area($request), (string) $request->input('text')));
    }

    public function store(QuickEntryRequest $request, Canvas $canvas): RedirectResponse
    {
        $this->quickEntry->commit($canvas, $this->area($request), (string) $request->input('text'));

        return back();
    }

    private function area(QuickEntryRequest $request): Area
    {
        return Area::query()->where('key', $request->string('area'))->firstOrFail();
    }
}
