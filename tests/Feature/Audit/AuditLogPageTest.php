<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_renamed_element_shows_old_and_new_name_with_user_and_canvas(): void
    {
        $admin = User::factory()->administrator()->create(['name' => 'Ada']);
        $canvas = Canvas::factory()->create(['name' => 'Strategie']);
        $element = Element::factory()->create(['canvas_id' => $canvas->id, 'name' => 'Inkoop']);
        AuditEntry::query()->delete();
        $this->actingAs($admin);
        $element->update(['name' => 'Inkoop en contractbeheer']);

        $this->get('/admin/audit-log')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLog')
                ->where('total', 1)
                ->where('entries.0.actor', ['kind' => 'user', 'name' => 'Ada'])
                ->where('entries.0.action', 'changed')
                ->where('entries.0.subject_type', 'element')
                ->where('entries.0.canvas', 'Strategie')
                ->where('entries.0.changes', [['field' => 'name', 'old' => 'Inkoop', 'new' => 'Inkoop en contractbeheer']]));
    }

    public function test_derived_columns_are_hidden(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $canvas = Canvas::factory()->create(['name' => 'Strategie']);
        AuditEntry::query()->delete();
        $canvas->update(['name' => 'Nieuw']);

        $this->get('/admin/audit-log')->assertInertia(fn (Assert $page) => $page
            ->where('entries.0.changes', fn ($changes) => array_column($changes->toArray(), 'field') === ['name']));
    }

    public function test_a_created_canvas_hides_scope_key_and_name_normalised(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        AuditEntry::query()->delete();
        Canvas::factory()->create(['name' => 'Strategie']);

        $this->get('/admin/audit-log')->assertInertia(fn (Assert $page) => $page
            ->where('entries.0.changes', fn ($changes) => ! array_intersect(['scope_key', 'name_normalised'], array_column($changes->toArray(), 'field'))));
    }

    public function test_a_deleted_user_and_the_system_are_labelled(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        AuditEntry::create(['occurred_at' => now(), 'actor_type' => 'user', 'actor_id' => null, 'action' => 'changed', 'subject_type' => 'canvas', 'subject_id' => '1', 'subject_label' => 'x', 'changes' => []]);
        AuditEntry::create(['occurred_at' => now()->subMinute(), 'actor_type' => 'system', 'action' => 'created', 'subject_type' => 'canvas', 'subject_id' => '1', 'subject_label' => 'x', 'changes' => []]);

        $this->get('/admin/audit-log')->assertInertia(fn (Assert $page) => $page
            ->where('entries.0.actor.kind', 'deleted')
            ->where('entries.1.actor.kind', 'system'));
    }

    public function test_filters_apply_and_an_invalid_filter_is_refused(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        AuditEntry::create(['occurred_at' => now(), 'actor_type' => 'system', 'action' => 'created', 'subject_type' => 'canvas', 'subject_id' => '1', 'subject_label' => 'x', 'changes' => []]);

        $this->get('/admin/audit-log?subject_type=element')->assertInertia(fn (Assert $page) => $page->where('total', 0));
        $this->get('/admin/audit-log?from=nonsense')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filterErrors.from', 'Vanaf datum: kies een geldige datum, bijvoorbeeld 31-12-2026'));
    }

    public function test_a_reversed_date_range_keeps_the_page_open_with_the_entered_dates(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/audit-log?from=2026-03-10&to=2026-03-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AuditLog')
            ->where('filterErrors.to', 'Tot en met datum: kies een datum op of na Vanaf datum')
            ->where('filters.from', '2026-03-10')
            ->where('filters.to', '2026-03-01'));
    }

    public function test_a_reader_gets_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/audit-log')->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/admin/audit-log')->assertRedirect('/sign-in');
    }
}
