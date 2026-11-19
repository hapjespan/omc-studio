<?php

namespace App\Services\Notices;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class LockoutCounter
{
    public const THRESHOLD = 10;

    private const KEY = 'lockouts';

    private const SECONDS = 3600;

    public function __construct(private readonly SystemNotices $notices) {}

    public function record(): void
    {
        $cache = Cache::store(config('cache.throttle_store'));
        $now = Carbon::now();
        $times = array_values(array_filter(
            (array) $cache->get(self::KEY, []),
            fn (int $time) => $time > $now->getTimestamp() - self::SECONDS,
        ));
        $times[] = $now->getTimestamp();
        $cache->put(self::KEY, $times, self::SECONDS);

        if (count($times) >= self::THRESHOLD) {
            $this->notices->record('repeated_lockouts', null, ['count' => count($times), 'lastTime' => $now->toIso8601String()]);
        }
    }
}
