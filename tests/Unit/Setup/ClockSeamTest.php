<?php

namespace Tests\Unit\Setup;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Tests\Support\FrozenClock;

class ClockSeamTest extends TestCase
{
    protected function tearDown(): void
    {
        FrozenClock::release();
    }

    public function test_time_is_frozen_until_it_is_advanced(): void
    {
        FrozenClock::freeze('2026-11-18 12:00:00');

        $first = now();
        usleep(2000);
        $this->assertTrue($first->equalTo(now()));

        FrozenClock::advance('12 hours');

        $this->assertSame('2026-11-19 00:00:00', now()->toDateTimeString());
    }

    public function test_release_returns_to_real_time(): void
    {
        FrozenClock::freeze('2000-01-01 00:00:00');
        FrozenClock::release();

        $this->assertGreaterThan(2000, Carbon::now()->year);
    }
}
