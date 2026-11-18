<?php

namespace Tests\Support;

use Illuminate\Support\Carbon;

class FrozenClock
{
    public static function freeze(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment));
    }

    public static function advance(string $interval): void
    {
        Carbon::setTestNow(Carbon::now()->modify('+'.$interval));
    }

    public static function release(): void
    {
        Carbon::setTestNow();
    }
}
