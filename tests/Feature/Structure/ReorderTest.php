<?php

namespace Tests\Feature\Structure;

use App\Models\AuditEntry;
use App\Models\Element;

class ReorderTest extends StructureTestCase
{
    public function test_up_swaps_with_the_previous_sibling_of_the_same_kind(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'Een');
        $cluster = $this->cluster($domain, 'Tussen');
        $second = $this->activity($domain, 'Twee');

        $this->tree()->reorder($second, 'up');

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(0, $cluster->fresh()->position);
    }

    public function test_down_swaps_with_the_next_sibling(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'Een');
        $second = $this->activity($domain, 'Twee');

        $this->tree()->reorder($first, 'down');

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(0, $second->fresh()->position);
    }

    public function test_it_is_a_no_op_at_the_ends_and_writes_nothing(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'Een');
        $last = $this->activity($domain, 'Twee');
        $before = AuditEntry::count();

        $this->tree()->reorder($first, 'up');
        $this->tree()->reorder($last, 'down');

        $this->assertSame(0, $first->fresh()->position);
        $this->assertSame(1, $last->fresh()->position);
        $this->assertSame($before, AuditEntry::count());
    }

    public function test_siblings_under_another_parent_and_the_unknown_domain_are_left_alone(): void
    {
        $domain = $this->domain('supporting_activities', 'Eerste');
        $other = $this->domain('supporting_activities', 'Tweede');
        $unknown = Element::factory()->create([
            'canvas_id' => $this->canvas->id,
            'area_id' => $domain->area_id,
            'name' => 'Onbekende allocatie',
            'is_unknown_allocation' => true,
            'position' => 1000000,
        ]);

        $this->tree()->reorder($other, 'down');

        $this->assertSame(1, $other->fresh()->position);
        $this->assertSame(1000000, $unknown->fresh()->position);
    }

    public function test_a_swap_is_audited(): void
    {
        $domain = $this->domain();
        $this->activity($domain, 'Een');
        $second = $this->activity($domain, 'Twee');
        $before = AuditEntry::count();

        $this->tree()->reorder($second, 'up');

        $this->assertSame($before + 2, AuditEntry::count());
    }

    public function test_an_unknown_direction_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tree()->reorder($this->domain(), 'sideways');
    }
}
