<?php

namespace Tests\Feature\Structure;

use App\Enums\ElementKind;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Canvas\UnknownAllocationDomain;

class MoveElementTest extends StructureTestCase
{
    public function test_an_activity_moves_to_a_domain_in_another_activity_area_with_its_fields_and_identifier(): void
    {
        $activity = $this->tree()->create(
            $this->canvas,
            $this->area('primary_activities'),
            ElementKind::Activity,
            $this->domain()->id,
            ['name' => 'Offreren', 'description' => 'Offertes maken', 'rationale' => 'Reden'],
        );
        $target = $this->domain('supporting_activities', 'Beheer');
        $this->activity($target, 'Bestellen');

        $moved = $this->tree()->move($activity, $target);

        $this->assertSame($activity->id, $moved->id);
        $this->assertSame($target->id, $moved->parent_id);
        $this->assertSame($this->area('supporting_activities')->id, $moved->area_id);
        $this->assertSame('Offreren', $moved->name);
        $this->assertSame('Offertes maken', $moved->description);
        $this->assertSame('Reden', $moved->rationale);
        $this->assertSame(1, $moved->position);
    }

    public function test_an_activity_moves_into_a_cluster_of_another_activity_area(): void
    {
        $activity = $this->activity($this->domain());
        $target = $this->cluster($this->domain('supporting_activities', 'Beheer'), 'Inkopen');

        $moved = $this->tree()->move($activity, $target);

        $this->assertSame($target->id, $moved->parent_id);
        $this->assertSame($this->area('supporting_activities')->id, $moved->area_id);
        $this->assertSame(0, $moved->position);
    }

    public function test_an_activity_moved_into_an_area_of_the_other_kind_is_refused(): void
    {
        $activity = $this->activity($this->domain());
        $group = $this->domain('actors', 'Leveranciers');

        $this->assertSame(
            'Verplaatsen kan alleen naar een area van dezelfde soort',
            $this->refusal(fn () => $this->tree()->move($activity, $group)),
        );
        $this->assertSame([$activity->parent_id, $activity->area_id], [$activity->fresh()->parent_id, $activity->fresh()->area_id]);
    }

    public function test_a_cluster_moves_to_another_domain_with_its_activities(): void
    {
        $cluster = $this->cluster($this->domain());
        $activities = array_map(fn (string $name) => $this->activity($cluster, $name), ['Adverteren', 'Bellen', 'Mailen']);
        $target = $this->domain('governing', 'Beleid');

        $moved = $this->tree()->move($cluster, $target);

        $this->assertSame($cluster->id, $moved->id);
        $this->assertSame($target->id, $moved->parent_id);
        $this->assertSame($this->area('governing')->id, $moved->area_id);

        foreach ($activities as $activity) {
            $this->assertSame($cluster->id, $activity->fresh()->parent_id);
            $this->assertSame($this->area('governing')->id, $activity->fresh()->area_id);
        }
    }

    public function test_a_domain_moves_to_another_area_with_its_clusters_and_activities(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);
        $underCluster = $this->activity($cluster, 'Adverteren');
        $underDomain = $this->activity($domain, 'Offreren');
        $this->domain('steering', 'Koersen');

        $moved = $this->tree()->move($domain, $this->area('steering'));

        $steering = $this->area('steering')->id;

        $this->assertSame($domain->id, $moved->id);
        $this->assertNull($moved->parent_id);
        $this->assertSame($steering, $moved->area_id);
        $this->assertSame(1, $moved->position);
        $this->assertSame([$steering, $steering, $steering], [$cluster->fresh()->area_id, $underCluster->fresh()->area_id, $underDomain->fresh()->area_id]);
        $this->assertSame([$domain->id, $cluster->id, $domain->id], [$cluster->fresh()->parent_id, $underCluster->fresh()->parent_id, $underDomain->fresh()->parent_id]);
        $this->assertSame(5, Element::query()->count());
    }

    public function test_the_new_position_ignores_the_unknown_allocation_domain_and_archived_siblings(): void
    {
        UnknownAllocationDomain::ensure($this->canvas);
        $this->domain('supporting_activities', 'Beheer')->update(['archived_at' => now()]);

        $moved = $this->tree()->move($this->domain(), $this->area('supporting_activities'));

        $this->assertSame(0, $moved->position);
    }

    public function test_a_list_area_domain_moved_to_an_activity_area_is_refused(): void
    {
        $group = $this->domain('actors', 'Leveranciers');

        $this->assertSame(
            'Verplaatsen kan alleen naar een area van dezelfde soort',
            $this->refusal(fn () => $this->tree()->move($group, $this->area('primary_activities'))),
        );
        $this->assertSame($this->area('actors')->id, $group->fresh()->area_id);
    }

    public function test_a_cluster_moved_into_a_domain_of_a_list_area_is_refused(): void
    {
        $cluster = $this->cluster($this->domain());
        $group = $this->domain('channels', 'Website');

        $this->assertSame(
            'Verplaatsen kan alleen naar een area van dezelfde soort',
            $this->refusal(fn () => $this->tree()->move($cluster, $group)),
        );
        $this->assertSame($this->area('primary_activities')->id, $cluster->fresh()->area_id);
    }

    public function test_a_move_into_direction_is_refused(): void
    {
        $activity = $this->activity($this->domain());

        $this->assertSame(
            'In Koers staan geen elementen',
            $this->refusal(fn () => $this->tree()->move($activity, $this->area('direction'))),
        );
    }

    public function test_a_name_taken_at_the_target_refuses_the_move_and_changes_nothing(): void
    {
        $activity = $this->activity($this->domain(), 'Offreren');
        $target = $this->domain('supporting_activities', 'Beheer');
        $this->activity($target, 'offreren ');

        $this->assertSame(
            'Naam: er staat al een element met deze naam op deze plek',
            $this->refusal(fn () => $this->tree()->move($activity, $target)),
        );
        $this->assertSame([$activity->parent_id, $activity->area_id], [$activity->fresh()->parent_id, $activity->fresh()->area_id]);
    }

    public function test_a_domain_moved_under_an_element_is_refused(): void
    {
        $domain = $this->domain();

        $this->assertSame(
            'Een domein hoort direct onder een area',
            $this->refusal(fn () => $this->tree()->move($domain, $this->domain('supporting_activities', 'Beheer'))),
        );
        $this->assertSame($this->area('primary_activities')->id, $domain->fresh()->area_id);
    }

    public function test_a_cluster_moved_into_a_cluster_is_refused(): void
    {
        $cluster = $this->cluster($this->domain());

        $this->assertSame(
            'Een cluster kan niet in een ander cluster',
            $this->refusal(fn () => $this->tree()->move($cluster, $this->cluster($this->domain('governing', 'Beleid'), 'Toezicht'))),
        );
    }

    public function test_a_move_to_a_target_of_another_canvas_is_refused(): void
    {
        $activity = $this->activity($this->domain());
        $elsewhere = $this->domain('supporting_activities', 'Beheer', Canvas::factory()->create());

        $this->assertSame(
            'Een activiteit hoort onder een domein of een cluster',
            $this->refusal(fn () => $this->tree()->move($activity, $elsewhere)),
        );
        $this->assertSame($this->area('primary_activities')->id, $activity->fresh()->area_id);
    }

    public function test_an_archived_canvas_takes_no_move(): void
    {
        $activity = $this->activity($this->domain());
        $target = $this->domain('supporting_activities', 'Beheer');
        $this->canvas->update(['archived_at' => now()]);

        $this->assertSame('Dit canvas is gearchiveerd', $this->refusal(fn () => $this->tree()->move($activity, $target)));
        $this->assertSame($this->area('primary_activities')->id, $activity->fresh()->area_id);
    }
}
