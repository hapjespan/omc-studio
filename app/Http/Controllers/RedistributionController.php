<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Models\Element;
use App\Services\Allocation\Redistribution;
use Illuminate\Http\JsonResponse;

class RedistributionController extends Controller
{
    public function __construct(private readonly Redistribution $redistribution) {}

    public function preview(Canvas $canvas, Element $element): JsonResponse
    {
        abort_unless($element->canvas_id === $canvas->id, 404);

        return response()->json($this->redistribution->preview($element));
    }
}
