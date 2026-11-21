<?php

namespace App\Http\Controllers;

use App\Http\Requests\DrawingMoveRequest;
use App\Models\Canvas;
use App\Services\Layout\DrawingMove;
use Illuminate\Http\JsonResponse;

class DrawingMoveController extends Controller
{
    public function __construct(private readonly DrawingMove $moves) {}

    public function __invoke(DrawingMoveRequest $request, Canvas $canvas): JsonResponse
    {
        return response()->json($this->moves->move($canvas, $request->validated()));
    }
}
