<?php

namespace Tests\Feature\Notices;

use App\Models\Canvas;
use App\Models\SystemNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemNoticePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_newest_first_with_details_and_count(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Strategie']);
        SystemNotice::factory()->create(['kind' => 'repeated_lockouts', 'last_occurred_at' => '2026-03-01 08:00:00']);
        SystemNotice::factory()->create([
            'kind' => 'closing_check_failed',
            'canvas_id' => $canvas->id,
            'details' => ['year' => 2026, 'filter' => [], 'check' => 'totals', 'differenceCents' => 1],
            'occurrences' => 10,
            'last_occurred_at' => '2026-03-01 15:40:00',
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/admin/system-notices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SystemNotices')
                ->where('total', 2)
                ->where('notices.0.kind', 'closing_check_failed')
                ->where('notices.0.canvas', 'Strategie')
                ->where('notices.0.count', 10)
                ->where('notices.0.last_time', '16:40')
                ->where('notices.0.details.differenceCents', 1)
                ->where('notices.1.kind', 'repeated_lockouts'));
    }

    public function test_filters_narrow_the_list(): void
    {
        $canvas = Canvas::factory()->create();
        SystemNotice::factory()->create(['kind' => 'closing_check_failed', 'canvas_id' => $canvas->id, 'last_occurred_at' => '2026-03-01 10:00:00']);
        SystemNotice::factory()->create(['kind' => 'repeated_lockouts', 'last_occurred_at' => '2026-04-01 10:00:00']);
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/system-notices?kind=repeated_lockouts')->assertInertia(fn (Assert $page) => $page->where('total', 1));
        $this->get('/admin/system-notices?canvas_id='.$canvas->id)->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('notices.0.kind', 'closing_check_failed'));
        $this->get('/admin/system-notices?from=2026-03-15&to=2026-04-30')->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('notices.0.kind', 'repeated_lockouts'));
        $this->get('/admin/system-notices?kind=bogus')->assertInertia(fn (Assert $page) => $page->where('total', 2)->has('filterErrors.kind'));
    }

    public function test_a_reader_gets_no_access_and_a_guest_is_sent_to_sign_in(): void
    {
        $notice = SystemNotice::factory()->create();

        $this->actingAs(User::factory()->create())->get('/admin/system-notices')->assertForbidden();
        $this->post("/admin/system-notices/{$notice->id}/seen")->assertForbidden();
        auth()->logout();
        $this->get('/admin/system-notices')->assertRedirect('/sign-in');
    }

    public function test_only_seen_can_be_set_and_no_edit_or_delete_route_exists(): void
    {
        $notice = SystemNotice::factory()->create(['details' => ['year' => 2026]]);
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $this->post("/admin/system-notices/{$notice->id}/seen", ['kind' => 'x', 'details' => ['year' => 1]])->assertRedirect();
        $notice->refresh();
        $this->assertNotNull($notice->seen_at);
        $this->assertSame($admin->id, $notice->seen_by);
        $this->assertSame('closing_check_failed', $notice->kind);
        $this->assertSame(['year' => 2026], $notice->details);

        $this->patch("/admin/system-notices/{$notice->id}")->assertNotFound();
        $this->delete("/admin/system-notices/{$notice->id}")->assertNotFound();
        $this->assertDatabaseCount('system_notices', 1);
    }

    public function test_the_warning_counts_unseen_notices_for_every_administrator_and_clears_after_seen(): void
    {
        $notice = SystemNotice::factory()->create();
        SystemNotice::factory()->create(['seen_at' => now()]);
        $first = User::factory()->administrator()->create();
        $second = User::factory()->administrator()->create();

        $this->actingAs($first)->get('/overview')->assertInertia(fn (Assert $page) => $page->where('pendingNotices', 1));
        $this->actingAs($second)->post("/admin/system-notices/{$notice->id}/seen");
        $this->actingAs($first)->get('/overview')->assertInertia(fn (Assert $page) => $page->where('pendingNotices', 0));
    }

    public function test_a_reader_is_not_shown_the_warning(): void
    {
        SystemNotice::factory()->create();

        $this->actingAs(User::factory()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page->where('pendingNotices', null));
    }
}
