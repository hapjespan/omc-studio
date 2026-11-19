<?php

namespace App\Http\Controllers;

use App\Http\Requests\CostRecordRequest;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Services\Finance\CostRecords;
use Illuminate\Http\RedirectResponse;

class CostRecordController extends Controller
{
    public function __construct(private readonly CostRecords $records) {}

    public function store(CostRecordRequest $request, Organisation $organisation): RedirectResponse
    {
        $this->records->create($organisation, $request->validated());

        return back()->with('status', __('cost_records.created'));
    }

    public function update(CostRecordRequest $request, CostRecord $costRecord): RedirectResponse
    {
        $this->records->update($costRecord, $request->validated());

        return back()->with('status', __('cost_records.saved'));
    }
}
