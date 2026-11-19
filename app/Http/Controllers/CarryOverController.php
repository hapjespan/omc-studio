<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Services\Allocation\CarryOver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CarryOverController extends Controller
{
    public function __construct(private readonly CarryOver $carryOver) {}

    public function run(Request $request, Canvas $canvas, int $year): RedirectResponse
    {
        $data = $request->validate(['source_year' => ['required', 'integer'], 'with_figures' => ['boolean']]);
        $years = $canvas->organisation->fiscalYears();
        $result = $this->carryOver->run($canvas, (clone $years)->where('year', $data['source_year'])->firstOrFail(), $years->where('year', $year)->firstOrFail(), (bool) ($data['with_figures'] ?? false));

        return back()->with('carry_over', $result + ['summary' => __('carry_over.summary', ['copied' => count($result['copied']), 'skipped' => count($result['skipped']), 'manual' => count($result['manual'])])]);
    }
}
