<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Services\Canvas\ViewData;
use Inertia\Inertia;
use Inertia\Response;

class CanvasViewController extends Controller
{
    public function __invoke(Canvas $canvas, ViewData $data): Response
    {
        return Inertia::render('Canvas/View', $data->for($canvas));
    }
}
