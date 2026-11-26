<?php

namespace App\Services\Accounts;

use App\Services\Notices\LockoutCounter;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use LogicException;

class Throttle
{
    public const LIMIT = 5;

    public const SECONDS = 60;

    private readonly Repository $cache;

    private readonly LockProvider $locks;

    public function __construct(private readonly string $namespace, private readonly bool $countsLockouts = false)
    {
        $this->cache = Cache::store(config('cache.throttle_store'));
        $store = $this->cache->getStore();
        $this->locks = $store instanceof LockProvider ? $store : throw new LogicException('The throttle store must support locks.');
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

    public function claim(string $identifier): bool
    {
        return $this->locked($identifier, function () use ($identifier): bool {
            if ($this->tooManyAttempts($identifier)) {
                return false;
            }

            $attempts = $this->key($identifier, 'attempts');
            $this->cache->add($attempts, 0, self::SECONDS);

            if ((int) $this->cache->increment($attempts) >= self::LIMIT) {
                $this->cache->forget($attempts);
                $this->cache->put($this->key($identifier, 'until'), Carbon::now()->getTimestamp() + self::SECONDS, self::SECONDS);
                $this->cache->put($this->key($identifier, 'pending'), true, self::SECONDS);
            }

            return true;
        });
    }

    public function fail(string $identifier): void
    {
        $this->locked($identifier, function () use ($identifier): void {
            if ($this->cache->pull($this->key($identifier, 'pending')) === true && $this->countsLockouts) {
                app(LockoutCounter::class)->record();
            }
        });
    }

    public function refund(string $identifier): void
    {
        $this->locked($identifier, function () use ($identifier): void {
            $attempts = $this->key($identifier, 'attempts');

            if ($this->cache->pull($this->key($identifier, 'pending')) === true) {
                $this->cache->forget($this->key($identifier, 'until'));
                $this->cache->put($attempts, self::LIMIT - 1, self::SECONDS);
            } elseif ((int) $this->cache->get($attempts, 0) > 0) {
                $this->cache->decrement($attempts);
            }
        });
    }

    public function clear(string $identifier): void
    {
        $this->cache->forget($this->key($identifier, 'attempts'));
        $this->cache->forget($this->key($identifier, 'until'));
        $this->cache->forget($this->key($identifier, 'pending'));
    }

    private function locked(string $identifier, Closure $work): mixed
    {
        return $this->locks->lock($this->key($identifier, 'lock'), 5)->block(5, $work);
    }

    private function key(string $identifier, string $part): string
    {
        return $this->namespace.':'.hash_hmac('sha256', $identifier, (string) config('app.key')).':'.$part;
    }
}
