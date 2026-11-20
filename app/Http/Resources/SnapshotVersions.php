<?php

namespace App\Http\Resources;

use App\Models\Canvas;
use App\Models\Snapshot;
use App\Support\Format;

class SnapshotVersions
{
    /** @return list<array{id: int, name: string, year: int, date: string, archived: bool}> */
    public static function for(Canvas $canvas, ?Snapshot $current = null): array
    {
        return Snapshot::where('canvas_id', $canvas->getKey())
            ->where(fn ($query) => $query->whereNull('archived_at')->orWhere('id', $current?->getKey()))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'name', 'fiscal_year', 'created_at', 'archived_at'])
            ->map(fn (Snapshot $version) => [
                'id' => $version->getKey(),
                'name' => $version->name,
                'year' => $version->fiscal_year,
                'date' => Format::formatDate($version->created_at->toIso8601String()),
                'archived' => $version->archived_at !== null,
            ])->values()->all();
    }
}
