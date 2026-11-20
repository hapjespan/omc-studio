<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Services\Finance\FiscalYears;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FiscalYearController extends Controller
{
    public function __construct(private readonly FiscalYears $years) {}

    public function store(Request $request, Organisation $organisation): RedirectResponse
    {
        $this->years->add($organisation, $request->input('year'));

        return back()->with('status', __('fiscal_years.added'));
    }

    public function destroy(FiscalYear $fiscalYear): RedirectResponse
    {
        $this->years->delete($fiscalYear);

        return back()->with('status', __('fiscal_years.deleted'));
    }
}
