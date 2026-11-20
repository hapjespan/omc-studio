<?php

namespace App\Http\Controllers;

use App\Http\Requests\AllocationRequest;
use App\Http\Resources\AllocationPage;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Allocation\AllocationSets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AllocationController extends Controller
{
    public function __construct(private readonly AllocationSets $sets) {}

    public function index(Request $request, Canvas $canvas, AllocationPage $page): Response
    {
        $canvas->loadMissing('organisation');
        $year = $canvas->organisation->fiscalYears()->where('year', $request->attributes->get('fiscal_year'))->first();
        $chosen = $request->query('cost_record');

        return Inertia::render('Finance/Allocation', $page->props($canvas, $year, is_string($chosen) ? $chosen : null));
    }

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
