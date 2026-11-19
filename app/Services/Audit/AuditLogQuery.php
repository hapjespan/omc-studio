<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Support\Format;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AuditLogQuery
{
    public const PER_PAGE = 50;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditEntry>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        return AuditEntry::query()
            ->with(['actor:id,name', 'canvas:id,name'])
            ->when(isset($filters['organisation_id']), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('organisation_id', $filters['organisation_id'])
                ->orWhereIn('canvas_id', Canvas::query()->where('organisation_id', $filters['organisation_id'])->select('id'))))
            ->when(isset($filters['canvas_id']), fn ($query) => $query->where('canvas_id', $filters['canvas_id']))
            ->when(isset($filters['subject_type']), fn ($query) => $query->where('subject_type', $filters['subject_type']))
            ->when(isset($filters['actor_id']), fn ($query) => $query->where('actor_id', $filters['actor_id']))
            ->when(isset($filters['from']), fn ($query) => $query->where('occurred_at', '>=', Carbon::parse($filters['from'], Format::TIMEZONE)->startOfDay()->utc()))
            ->when(isset($filters['to']), fn ($query) => $query->where('occurred_at', '<', Carbon::parse($filters['to'], Format::TIMEZONE)->addDay()->startOfDay()->utc()))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
