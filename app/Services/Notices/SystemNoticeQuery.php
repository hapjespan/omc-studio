<?php

namespace App\Services\Notices;

use App\Models\SystemNotice;
use App\Support\DateRangeFilter;
use Illuminate\Database\Eloquent\Collection;

class SystemNoticeQuery
{
    /**
     * @param  array<string, mixed>  $valid
     * @return Collection<int, SystemNotice>
     */
    public function get(array $valid): Collection
    {
        return SystemNotice::query()
            ->when($valid['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($valid['canvas_id'] ?? null, fn ($query, $canvas) => $query->where('canvas_id', $canvas))
            ->when($valid['from'] ?? null, fn ($query, $from) => $query->where('last_occurred_at', '>=', DateRangeFilter::start($from)))
            ->when($valid['to'] ?? null, fn ($query, $to) => $query->where('last_occurred_at', '<', DateRangeFilter::end($to)))
            ->orderByDesc('last_occurred_at')->orderByDesc('id')
            ->get();
    }
}
