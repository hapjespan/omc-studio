<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use Inertia\Inertia;
use Inertia\Response;

class CanvasViewController extends Controller
{
    public function __invoke(Canvas $canvas): Response
    {
        return Inertia::render('Canvas/View', [
            'canWrite' => false,
        ]);
    }
}
