<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Models\FiscalYear;
use App\Services\Allocation\Finalisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinaliseController extends Controller
{
    public function __construct(private readonly Finalisation $finalisation) {}

    public function store(Canvas $canvas, int $year): RedirectResponse
    {
        $this->finalisation->finalise($canvas, $this->fiscalYear($canvas, $year));

        return back()->with('status', __('finalise.done', ['year' => $year]));
    }

    public function reopen(Request $request, Canvas $canvas, int $year): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);
        $this->finalisation->reopen($canvas, $this->fiscalYear($canvas, $year));

        return back()->with('status', __('finalise.reopened', ['year' => $year]));
    }

    private function fiscalYear(Canvas $canvas, int $year): FiscalYear
    {
        return FiscalYear::where('organisation_id', $canvas->organisation_id)->where('year', $year)->firstOrFail();
    }
}
