<?php

namespace App\Services\Accounts;

use App\Services\Notices\LockoutCounter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class Throttle
{
    public const LIMIT = 5;

    public const SECONDS = 60;

    private readonly Repository $cache;

    public function __construct(private readonly string $namespace, private readonly bool $countsLockouts = false)
    {
        $this->cache = Cache::store(config('cache.throttle_store'));
    }

    public function secondsLeft(string $identifier): int
    {
        $until = (int) $this->cache->get($this->key($identifier, 'until'), 0);

        return max(0, $until - Carbon::now()->getTimestamp());
    }

    public function message(string $identifier): string
    {
        $seconds = $this->secondsLeft($identifier);

        return trans_choice('auth.throttled', $seconds, ['seconds' => $seconds]);
    }

    public function tooManyAttempts(string $identifier): bool
    {
        return $this->secondsLeft($identifier) > 0;
    }

    public function hit(string $identifier): void
    {
        $attempts = $this->key($identifier, 'attempts');
        $this->cache->add($attempts, 0, self::SECONDS);

        if ((int) $this->cache->increment($attempts) < self::LIMIT) {
            return;
        }

        $this->cache->forget($attempts);
        $this->cache->put($this->key($identifier, 'until'), Carbon::now()->getTimestamp() + self::SECONDS, self::SECONDS);

        if ($this->countsLockouts) {
            app(LockoutCounter::class)->record();
        }
    }

    public function clear(string $identifier): void
    {
        $this->cache->forget($this->key($identifier, 'attempts'));
        $this->cache->forget($this->key($identifier, 'until'));
    }

    private function key(string $identifier, string $part): string
    {
        return $this->namespace.':'.hash_hmac('sha256', $identifier, (string) config('app.key')).':'.$part;
    }
}
