<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSnapshotRequest;
use App\Http\Requests\UpdateSnapshotRequest;
use App\Models\Canvas;
use App\Models\Snapshot;
use App\Services\Snapshot\Snapshots;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SnapshotController extends Controller
{
    public function __construct(private readonly Snapshots $snapshots) {}

    public function store(StoreSnapshotRequest $request, Canvas $canvas): RedirectResponse
    {
        $snapshot = $this->snapshots->take(
            $canvas,
            $request->fiscalYear(),
            $request->string('name')->toString(),
            $request->string('remark')->toString() ?: null,
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
