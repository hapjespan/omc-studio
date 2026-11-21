<?php

namespace Tests\Feature\Notices;

use App\Models\SystemNotice;
use App\Services\Accounts\Throttle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class LockoutNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store(config('cache.throttle_store'))->flush();
        FrozenClock::freeze('2026-03-01 10:00:00');
    }

    protected function tearDown(): void
    {
        FrozenClock::release();

        parent::tearDown();
    }

    private function lockOut(string $namespace, string $identifier): void
    {
        $throttle = new Throttle($namespace, in_array($namespace, ['sign-in', 'second-factor'], true));

        for ($i = 0; $i < Throttle::LIMIT; $i++) {
            $throttle->claim($identifier);
            $throttle->fail($identifier);
        }
    }

    public function test_nine_lockouts_in_an_hour_give_no_notice(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->lockOut('sign-in', 'user'.$i);
        }

        $this->assertSame(0, SystemNotice::count());
    }

    public function test_the_tenth_lockout_records_one_notice_with_count_and_last_time_and_no_identity(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->lockOut($i % 2 === 0 ? 'sign-in' : 'second-factor', 'user'.$i.'@example.test');
            FrozenClock::advance('1 minute');
        }

        $notice = SystemNotice::sole();
        $this->assertSame('repeated_lockouts', $notice->kind);
        $this->assertSame(10, $notice->details['count']);
        $this->assertSame('2026-03-01T10:09:00+00:00', $notice->details['lastTime']);
        $this->assertStringNotContainsString('example.test', (string) json_encode($notice->details));
        $this->assertNull($notice->canvas_id);
    }

    public function test_lockouts_older_than_an_hour_do_not_count(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->lockOut('sign-in', 'user'.$i);
        }

        FrozenClock::advance('61 minutes');
        $this->lockOut('sign-in', 'late');

        $this->assertSame(0, SystemNotice::count());
    }

    public function test_other_lockouts_are_not_counted(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->lockOut('reauthenticate', 'user'.$i);
        }

        $this->assertSame(0, SystemNotice::count());
    }
}
