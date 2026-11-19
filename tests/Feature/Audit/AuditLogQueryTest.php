<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function entry(array $attributes = []): AuditEntry
    {
        return AuditEntry::create($attributes + [
            'occurred_at' => now(),
            'actor_type' => 'user',
            'action' => 'changed',
            'subject_type' => 'element',
            'subject_id' => '1',
            'subject_label' => 'Inkoop',
            'changes' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    private function ids(array $filters = []): array
    {
        return app(AuditLogQuery::class)->paginate($filters)->getCollection()->pluck('id')->all();
    }

    public function test_newest_entry_comes_first_with_the_count(): void
    {
        $old = $this->entry(['occurred_at' => now()->subDay()]);
        $new = $this->entry();

        $page = app(AuditLogQuery::class)->paginate([]);

        $this->assertSame([$new->id, $old->id], $page->getCollection()->pluck('id')->all());
        $this->assertSame(2, $page->total());
    }

    public function test_filters_by_canvas_and_kind(): void
    {
        $hit = $this->entry(['canvas_id' => 5, 'subject_type' => 'statement']);
        $this->entry(['canvas_id' => 6, 'subject_type' => 'statement']);
        $this->entry(['canvas_id' => 5, 'subject_type' => 'element']);

        $this->assertSame([$hit->id], $this->ids(['canvas_id' => 5, 'subject_type' => 'statement']));
    }

    public function test_filters_by_user(): void
    {
        $user = User::factory()->create();
        $hit = $this->entry(['actor_id' => $user->id]);
        $this->entry();

        $this->assertSame([$hit->id], $this->ids(['actor_id' => $user->id]));
    }

    public function test_organisation_filter_joins_entries_that_carry_only_a_canvas(): void
    {
        $organisation = Organisation::factory()->create();
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        $other = Canvas::factory()->create();
        AuditEntry::query()->delete();
        $direct = $this->entry(['organisation_id' => $organisation->id, 'subject_type' => 'canvas']);
        $viaCanvas = $this->entry(['canvas_id' => $canvas->id]);
        $this->entry(['canvas_id' => $other->id]);

        $this->assertEqualsCanonicalizing([$direct->id, $viaCanvas->id], $this->ids(['organisation_id' => $organisation->id]));
    }

    public function test_filters_by_date_range_in_amsterdam_days(): void
    {
        $this->entry(['occurred_at' => '2026-03-04 22:30:00']);
        $first = $this->entry(['occurred_at' => '2026-03-04 23:30:00']);
        $last = $this->entry(['occurred_at' => '2026-03-10 22:30:00']);
        $this->entry(['occurred_at' => '2026-03-10 23:30:00']);

        $this->assertEqualsCanonicalizing([$first->id, $last->id], $this->ids(['from' => '2026-03-05', 'to' => '2026-03-10']));
    }

    public function test_organisation_filter_combines_with_other_filters(): void
    {
        $organisation = Organisation::factory()->create();
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        AuditEntry::query()->delete();
        $hit = $this->entry(['canvas_id' => $canvas->id, 'subject_type' => 'element']);
        $this->entry(['canvas_id' => $canvas->id, 'subject_type' => 'statement']);
        $this->entry(['subject_type' => 'element']);

        $this->assertSame([$hit->id], $this->ids(['organisation_id' => $organisation->id, 'subject_type' => 'element']));
    }

    public function test_empty_filter_values_are_ignored(): void
    {
        $this->entry();

        $this->assertCount(1, $this->ids(['canvas_id' => '', 'actor_id' => null, 'from' => '']));
    }
}
