<?php

namespace Tests\Unit\Notices;

use App\Models\Canvas;
use App\Models\SystemNotice;
use App\Models\User;
use App\Services\Notices\SystemNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class SystemNoticesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FrozenClock::release();

        parent::tearDown();
    }

    /** @param array<string, mixed> $details */
    private function record(Canvas $canvas, array $details = []): SystemNotice
    {
        return app(SystemNotices::class)->record('closing_check_failed', $canvas->id, $details + ['year' => 2026, 'check' => 'budget']);
    }

    public function test_the_same_fact_while_not_seen_counts_occurrences_instead_of_adding_rows(): void
    {
        $canvas = Canvas::factory()->create();
        FrozenClock::freeze('2026-03-01 10:00:00');
        $this->record($canvas);

        for ($i = 0; $i < 9; $i++) {
            FrozenClock::advance('1 minute');
            $notice = $this->record($canvas, ['differenceCents' => $i]);
        }

        $this->assertSame(1, SystemNotice::count());
        $this->assertSame(10, $notice->occurrences);
        $this->assertSame('2026-03-01 10:00:00', $notice->first_occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-01 10:09:00', $notice->last_occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame(8, $notice->details['differenceCents']);
    }

    public function test_a_different_canvas_year_filter_or_check_is_a_different_fact(): void
    {
        $canvas = Canvas::factory()->create();
        $this->record($canvas);
        $this->record(Canvas::factory()->create());
        $this->record($canvas, ['year' => 2027]);
        $this->record($canvas, ['check' => 'forecast']);
        $this->record($canvas, ['filter' => 'personnel']);

        $this->assertSame(5, SystemNotice::count());
    }

    public function test_a_seen_fact_starts_a_new_notice(): void
    {
        $canvas = Canvas::factory()->create();
        $first = $this->record($canvas);
        $first->update(['seen_at' => now(), 'seen_by' => User::factory()->create()->id]);

        $second = $this->record($canvas);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, $second->occurrences);
        $this->assertSame(2, SystemNotice::count());
        $this->assertSame(1, $first->fresh()->occurrences);
    }

    public function test_details_keep_only_non_personal_keys(): void
    {
        $notice = $this->record(Canvas::factory()->create(), ['email' => 'a@b.nl', 'user_id' => 4, 'count' => 10]);

        $this->assertEqualsCanonicalizing(['year', 'check', 'count'], array_keys($notice->details));
    }

    public function test_a_notice_without_a_canvas_is_one_fact_per_kind(): void
    {
        app(SystemNotices::class)->record('repeated_lockouts', null, ['count' => 10]);
        $notice = app(SystemNotices::class)->record('repeated_lockouts', null, ['count' => 12]);

        $this->assertSame(1, SystemNotice::count());
        $this->assertSame(2, $notice->occurrences);
        $this->assertNull($notice->canvas_id);
    }
}
