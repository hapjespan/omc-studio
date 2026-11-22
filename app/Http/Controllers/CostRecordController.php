<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Enums\CostKind;
use App\Http\Requests\CostRecordRequest;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Finance\CostRecordList;
use App\Services\Finance\CostRecords;
use App\Services\Finance\FigureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostRecordController extends Controller
{
    public function __construct(private readonly CostRecords $records, private readonly CostRecordList $list) {}

    public function index(Request $request, Organisation $organisation): Response
    {
        return Inertia::render('Finance/CostRecords', $this->chunked($this->props($request, $organisation)));
    }

    public function canvasIndex(Request $request, Canvas $canvas, FigureService $figures): Response
    {
        $canvas->loadMissing('organisation');
        $props = $this->props($request, $canvas->organisation, $canvas);
        $result = $props['year'] === null ? null : $figures->figures($canvas, $props['year']);

        return Inertia::render('Finance/CostRecords', $this->chunked($props) + [
            'cards' => $result === null ? null : [
                'budgetCents' => $result->canvasTotal['budget'],
                'forecastCents' => $result->canvasTotal['forecast'],
                'allocated' => $result->sidePanel['allocated'],
                'unknown' => $result->sidePanel['unknown'],
            ],
        ]);
    }

    private const FIRST_CHUNK = 60;

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function chunked(array $props): array
    {
        $rows = $props['rows'];

        return [...$props, 'rows' => array_slice($rows, 0, self::FIRST_CHUNK), 'moreRows' => Inertia::defer(fn () => array_slice($rows, self::FIRST_CHUNK), 'rest')];
    }

    /** @return array<string, mixed> */
    private function props(Request $request, Organisation $organisation, ?Canvas $canvas = null): array
    {
        $years = $organisation->fiscalYears()->orderBy('year')->get();
        $year = $years->firstWhere('year', $request->attributes->get('fiscal_year'));

        return $this->list->run($organisation, $year, $request->query(), $canvas) + [
            'organisation' => ['id' => $organisation->id, 'name' => $organisation->name, 'archived' => $organisation->archived_at !== null],
            'year' => $year?->year,
            'canvases' => $organisation->canvases()->where('kind', CanvasKind::Canvas)->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->all(),
            'finalised' => $this->finalised($organisation),
            'years' => $years->map(fn ($fiscalYear) => ['id' => $fiscalYear->id, 'year' => $fiscalYear->year])->all(),
            'owners' => $organisation->owners()->orderBy('name_normalised')->get(['id', 'name'])->all(),
            'applications' => $organisation->applications()->orderBy('name_normalised')->get(['id', 'name'])->all(),
            'costKinds' => array_map(fn (CostKind $kind) => $kind->value, CostKind::cases()),
        ];
    }

    /** @return list<array{canvas_id: int, year: int, at: string, by: string|null}> */
    private function finalised(Organisation $organisation): array
    {
        return FinalisedYear::with(['fiscalYear', 'finaliser'])
            ->whereIn('canvas_id', $organisation->canvases()->select('id'))
            ->orderBy('id')->get()
            ->map(fn (FinalisedYear $row) => ['canvas_id' => $row->canvas_id, 'year' => $row->fiscalYear->year, 'at' => $row->finalised_at->format('d-m-Y'), 'by' => $row->finaliser?->name ?? ($row->imported ? null : __('users.deleted_user'))])->all();
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
        $identifiers = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['uuid']])['ids'];

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
