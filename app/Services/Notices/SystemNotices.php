<?php

namespace App\Services\Notices;

use App\Models\SystemNotice;
use Illuminate\Support\Facades\DB;

class SystemNotices
{
    private const FACT_KEYS = ['year', 'filter', 'check', 'snapshotId'];

    private const DETAIL_KEYS = ['year', 'filter', 'check', 'snapshotId', 'snapshot', 'differenceCents', 'count', 'lastTime'];

    /**
     * @param  array<string, mixed>  $filter
     * @param  array{check: string, differenceCents: int}  $failure
     */
    public function recordClosingCheckFailed(int $canvasId, int $year, array $filter, array $failure): SystemNotice
    {
        $filter = array_map(fn ($value) => (string) $value, array_filter($filter, fn ($value) => $value !== null && $value !== ''));
        ksort($filter);

        return $this->record('closing_check_failed', $canvasId, [
            'year' => $year,
            'filter' => $filter,
            'check' => $failure['check'],
            'differenceCents' => $failure['differenceCents'],
        ]);
    }

    /** @param array<string, mixed> $details */
    public function record(string $kind, ?int $canvasId = null, array $details = []): SystemNotice
    {
        $details = array_intersect_key($details, array_flip(self::DETAIL_KEYS));
        $fact = array_intersect_key($details, array_flip(self::FACT_KEYS));
        ksort($fact);
        $fingerprint = sha1(json_encode([$kind, $canvasId, $fact], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($kind, $canvasId, $details, $fingerprint) {
            $notice = SystemNotice::where('fingerprint', $fingerprint)->whereNull('seen_at')->first();

            if ($notice === null) {
                return SystemNotice::create([
                    'kind' => $kind,
                    'fingerprint' => $fingerprint,
                    'canvas_id' => $canvasId,
                    'details' => $details,
                    'first_occurred_at' => now(),
                    'last_occurred_at' => now(),
                ]);
            }

            $notice->increment('occurrences', 1, ['details' => $details, 'last_occurred_at' => now()]);

            return $notice;
        });
    }
}
