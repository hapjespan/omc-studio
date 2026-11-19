<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSnapshotRequest;
use App\Models\Canvas;
use App\Services\Snapshot\Snapshots;
use Illuminate\Http\RedirectResponse;

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
}
