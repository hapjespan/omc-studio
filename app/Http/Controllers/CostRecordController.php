<?php

namespace App\Http\Controllers;

use App\Http\Requests\CostRecordRequest;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Finance\CostRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function destroy(Request $request, CostRecord $costRecord): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $this->records->delete($costRecord, $user);

        return back()->with('undo', ['token' => $token, 'message' => __('cost_records.deleted')]);
    }

    public function archive(CostRecord $costRecord): RedirectResponse
    {
        $this->records->archive($costRecord);

        return back()->with('status', __('cost_records.archived'));
    }

    public function restore(CostRecord $costRecord): RedirectResponse
    {
        $this->records->restore($costRecord);

        return back()->with('status', __('cost_records.restored'));
    }
}
