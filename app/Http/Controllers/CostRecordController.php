<?php

namespace App\Http\Controllers;

use App\Enums\CostKind;
use App\Http\Requests\CostRecordRequest;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Finance\CostRecordList;
use App\Services\Finance\CostRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostRecordController extends Controller
{
    public function __construct(private readonly CostRecords $records, private readonly CostRecordList $list) {}

    public function index(Request $request, Organisation $organisation): Response
    {
        $years = $organisation->fiscalYears()->orderBy('year')->get();
        $year = $years->firstWhere('year', $request->attributes->get('fiscal_year'));

        return Inertia::render('Finance/CostRecords', $this->list->run($organisation, $year, $request->query()) + [
            'organisation' => ['id' => $organisation->id, 'name' => $organisation->name, 'archived' => $organisation->archived_at !== null],
            'year' => $year?->year,
            'years' => $years->map(fn ($fiscalYear) => ['id' => $fiscalYear->id, 'year' => $fiscalYear->year])->all(),
            'owners' => $organisation->owners()->orderBy('name_normalised')->get(['id', 'name'])->all(),
            'applications' => $organisation->applications()->orderBy('name_normalised')->get(['id', 'name'])->all(),
            'costKinds' => array_map(fn (CostKind $kind) => $kind->value, CostKind::cases()),
        ]);
    }

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

    public function bulkDestroy(Request $request, Organisation $organisation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array<int, string> $identifiers */
        $identifiers = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['string']])['ids'];

        $result = $this->records->deleteMany($organisation, $organisation->costRecords()->whereKey($identifiers)->get(), $user);

        return back()
            ->with('bulk', ['done' => $result['done'], 'skipped' => $result['skipped']])
            ->with('undo', $result['token'] === null ? null : ['token' => $result['token'], 'message' => trans_choice('cost_records.deleted_count', $result['done'])]);
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
