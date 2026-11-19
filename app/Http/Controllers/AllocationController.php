<?php

namespace App\Http\Controllers;

use App\Http\Requests\AllocationRequest;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Allocation\AllocationSets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function __construct(private readonly AllocationSets $sets) {}

    public function update(AllocationRequest $request, Canvas $canvas, int $year, CostRecord $costRecord): RedirectResponse
    {
        abort_unless($costRecord->organisation_id === $canvas->organisation_id, 404);

        $this->sets->replace($canvas, $costRecord, $this->fiscalYear($canvas, $year), $request->lines());

        return back()->with('status', __('allocation.saved'));
    }

    public function destroy(Request $request, Canvas $canvas, int $year, CostRecord $costRecord): RedirectResponse
    {
        abort_unless($costRecord->organisation_id === $canvas->organisation_id, 404);
        /** @var User $user */
        $user = $request->user();

        $token = $this->sets->clear($canvas, $costRecord, $this->fiscalYear($canvas, $year), $user);

        return back()->with('undo', ['token' => $token, 'message' => __('allocation.cleared')]);
    }

    private function fiscalYear(Canvas $canvas, int $year): FiscalYear
    {
        return FiscalYear::query()->where('organisation_id', $canvas->organisation_id)->where('year', $year)->firstOrFail();
    }
}
