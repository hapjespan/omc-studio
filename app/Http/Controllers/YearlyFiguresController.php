<?php

namespace App\Http\Controllers;

use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Finance\YearlyFigures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class YearlyFiguresController extends Controller
{
    public function __construct(private readonly YearlyFigures $figures) {}

    public function update(Request $request, CostRecord $costRecord, int $year): RedirectResponse
    {
        $this->figures->save($costRecord, $this->fiscalYear($costRecord, $year), $request->all());

        return back()->with('status', __('yearly_figures.saved'));
    }

    public function destroy(Request $request, CostRecord $costRecord, int $year): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $figure = $costRecord->yearlyFigures()->where('fiscal_year_id', $this->fiscalYear($costRecord, $year)->getKey())->firstOrFail();

        $token = $this->figures->delete($figure, $user);

        return back()->with('undo', ['token' => $token, 'message' => __('yearly_figures.deleted'), 'scope' => 'figures']);
    }

    private function fiscalYear(CostRecord $costRecord, int $year): FiscalYear
    {
        return $costRecord->organisation->fiscalYears()->where('year', $year)->firstOrFail();
    }
}
