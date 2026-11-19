<?php

namespace App\Services\Notices;

use App\Models\SystemNotice;
use Illuminate\Support\Facades\DB;

class SystemNotices
{
    private const FACT_KEYS = ['year', 'filter', 'check', 'snapshot'];

    private const DETAIL_KEYS = ['year', 'filter', 'check', 'snapshot', 'differenceCents', 'count', 'lastTime'];

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

            $notice->update([
                'details' => $details,
                'occurrences' => $notice->occurrences + 1,
                'last_occurred_at' => now(),
            ]);

            return $notice;
        });
    }
}
