<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Http\Requests\StoreSnapshotRequest;
use App\Http\Requests\UpdateSnapshotRequest;
use App\Models\Canvas;
use App\Models\Snapshot;
use App\Services\Lists\ListQuery;
use App\Services\Snapshot\Snapshots;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SnapshotController extends Controller
{
    public function __construct(private readonly Snapshots $snapshots) {}

    public function index(Request $request, Canvas $canvas): Response
    {
        abort_if($canvas->kind === CanvasKind::Template, 404);

        $list = (new ListQuery(['name'], ['name', 'fiscal_year', 'created_at'], [], 'archived_at'))->run(
            Snapshot::query()
                ->where('canvas_id', $canvas->getKey())
                ->select(['id', 'name', 'fiscal_year', 'remark', 'archived_at', 'created_at', 'imported', DB::raw('(SELECT name FROM users WHERE users.id = snapshots.created_by) AS maker')])
                ->orderByDesc('created_at')->orderByDesc('id'),
            $request->query(),
        );

        $list['rows'] = array_map(fn (array $row) => [...$row, 'created' => Format::formatDate(Carbon::parse($row['created_at'])->toIso8601String()), 'imported' => (bool) $row['imported']], $list['rows']);

        return Inertia::render('Versions/Index', $list + [
            'canWrite' => $request->user()?->can('administer') ?? false,
            'years' => $canvas->organisation->fiscalYears()->orderBy('year')->pluck('year')->all(),
        ]);
    }

    public function store(StoreSnapshotRequest $request, Canvas $canvas): RedirectResponse
    {
        $snapshot = $this->snapshots->take(
            $canvas,
            $request->fiscalYear(),
            $request->string('name')->toString(),
            $request->filled('remark') ? $request->string('remark')->toString() : null,
        );

        return back()->with('status', __('snap.created', ['name' => $snapshot->name]));
    }

    public function update(UpdateSnapshotRequest $request, Snapshot $snapshot): RedirectResponse
    {
        $this->snapshots->update($snapshot, $request->validated());

        return back()->with('status', __('snap.updated'));
    }

    public function archive(Snapshot $snapshot): RedirectResponse
    {
        $this->snapshots->archive($snapshot);

        return back()->with('status', __('snap.archived', ['name' => $snapshot->name]));
    }

    public function restore(Snapshot $snapshot): RedirectResponse
    {
        $this->snapshots->restore($snapshot);

        return back()->with('status', __('snap.restored', ['name' => $snapshot->name]));
    }

    public function destroy(Request $request, Snapshot $snapshot): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);
        $this->snapshots->delete($snapshot);

        return back()->with('status', __('snap.deleted', ['name' => $snapshot->name]));
    }
}
