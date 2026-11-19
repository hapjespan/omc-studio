<?php

namespace Tests\Feature\Structure;

use App\Enums\ElementKind;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Canvas\UnknownAllocationDomain;

class CreateElementTest extends StructureTestCase
{
    public function test_a_domain_lands_directly_under_an_activity_area(): void
    {
        $domain = $this->domain();

        $this->assertSame($this->area('primary_activities')->id, $domain->area_id);
        $this->assertNull($domain->parent_id);
        $this->assertSame(ElementKind::Domain, $domain->kind);
        $this->assertSame(0, $domain->position);
        $this->assertSame('inkoop', $domain->name_normalised);
        $this->assertSame($this->canvas->id, $domain->canvas_id);
    }

    public function test_a_group_lands_directly_under_a_list_area_and_takes_its_items(): void
    {
        $group = $this->domain('actors', 'Leveranciers');

        $item = $this->tree()->create($this->canvas, $this->area('actors'), ElementKind::Activity, $group->id, ['name' => 'Drukkerij']);

        $this->assertSame($this->area('actors')->id, $item->area_id);
        $this->assertSame($group->id, $item->parent_id);
    }

    public function test_a_cluster_lands_under_a_domain_and_carries_the_fields_through(): void
    {
        $cluster = $this->tree()->create(
            $this->canvas,
            $this->area('primary_activities'),
            ElementKind::Cluster,
            $this->domain()->id,
            ['name' => 'Werving', 'description' => 'Alles rond werving', 'rationale' => 'Samenhang', 'icon' => 'users', 'canvas_id' => 'ignored'],
        );

        $this->assertSame(ElementKind::Cluster, $cluster->kind);
        $this->assertSame('Alles rond werving', $cluster->description);
        $this->assertSame('Samenhang', $cluster->rationale);
        $this->assertSame('users', $cluster->icon);
        $this->assertSame($this->canvas->id, $cluster->canvas_id);
    }

    public function test_an_activity_lands_under_a_domain_or_under_a_cluster(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);

        $underDomain = $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, $domain->id, ['name' => 'Offreren']);
        $underCluster = $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, $cluster->id, ['name' => 'Adverteren']);

        $this->assertSame($domain->id, $underDomain->parent_id);
        $this->assertSame($cluster->id, $underCluster->parent_id);
        $this->assertSame(0, $underCluster->position);
    }

    public function test_the_position_counts_on_from_the_last_active_sibling(): void
    {
        $first = $this->domain('supporting_activities', 'Inkoop');
        $second = $this->domain('supporting_activities', 'Beheer');

        $this->assertSame([0, 1], [$first->position, $second->position]);

        $activity = $this->tree()->create($this->canvas, $this->area('supporting_activities'), ElementKind::Activity, $first->id, ['name' => 'Bestellen']);

        $this->assertSame(0, $activity->position);
    }

    public function test_the_position_ignores_the_unknown_allocation_domain_and_archived_siblings(): void
    {
        UnknownAllocationDomain::ensure($this->canvas);
        $this->domain('supporting_activities', 'Inkoop')->update(['archived_at' => now()]);

        $this->assertSame(0, $this->domain('supporting_activities', 'Beheer')->position);
    }

    public function test_an_activity_without_a_parent_is_refused(): void
    {
        $this->assertSame(
            'Een activiteit hoort onder een domein of een cluster',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, null, ['name' => 'Offreren'])),
        );
        $this->assertSame(0, Element::query()->count());
    }

    public function test_an_activity_under_a_missing_parent_is_refused(): void
    {
        $this->assertSame(
            'Een activiteit hoort onder een domein of een cluster',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, 'bf1f4dd6-0000-4000-8000-000000000000', ['name' => 'Offreren'])),
        );
        $this->assertSame(0, Element::query()->count());
    }

    public function test_an_activity_under_another_activity_is_refused(): void
    {
        $activity = $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, $this->domain()->id, ['name' => 'Offreren']);

        $this->assertSame(
            'Een activiteit hoort onder een domein of een cluster',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, $activity->id, ['name' => 'Bellen'])),
        );
        $this->assertSame(2, Element::query()->count());
    }

    public function test_an_activity_under_a_domain_of_another_canvas_is_refused(): void
    {
        $elsewhere = $this->domain('primary_activities', 'Inkoop', Canvas::factory()->create());

        $this->assertSame(
            'Een activiteit hoort onder een domein of een cluster',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, $elsewhere->id, ['name' => 'Offreren'])),
        );
        $this->assertSame(1, Element::query()->count());
    }

    public function test_an_activity_under_a_parent_of_another_area_is_refused(): void
    {
        $domain = $this->domain('supporting_activities', 'Beheer');

        $this->assertSame(
            'Een activiteit hoort onder een domein of een cluster',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Activity, $domain->id, ['name' => 'Offreren'])),
        );
        $this->assertSame(1, Element::query()->count());
    }

    public function test_a_cluster_under_a_cluster_is_refused(): void
    {
        $cluster = $this->cluster($this->domain());

        $this->assertSame(
            'Een cluster kan niet in een ander cluster',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Cluster, $cluster->id, ['name' => 'Campagnes'])),
        );
        $this->assertSame(2, Element::query()->count());
    }

    public function test_a_cluster_without_a_domain_as_parent_is_refused(): void
    {
        $this->assertSame(
            'Een cluster hoort onder een domein',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Cluster, null, ['name' => 'Werving'])),
        );
        $this->assertSame(0, Element::query()->count());
    }

    public function test_a_cluster_in_a_list_area_is_refused(): void
    {
        foreach (['actors', 'channels', 'value_propositions'] as $key) {
            $group = $this->domain($key, 'Groep '.$key);

            $this->assertSame(
                'In deze area bestaan geen clusters',
                $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area($key), ElementKind::Cluster, $group->id, ['name' => 'Werving'])),
            );
        }

        $this->assertSame(3, Element::query()->count());
    }

    public function test_direction_takes_no_element_at_all(): void
    {
        foreach (ElementKind::cases() as $kind) {
            $this->assertSame(
                'In Koers staan geen elementen',
                $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('direction'), $kind, null, ['name' => 'Missie'])),
            );
        }

        $this->assertSame(0, Element::query()->count());
    }

    public function test_a_domain_with_a_parent_is_refused(): void
    {
        $domain = $this->domain();

        $this->assertSame(
            'Een domein hoort direct onder een area',
            $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Domain, $domain->id, ['name' => 'Verkoop'])),
        );
        $this->assertSame(1, Element::query()->count());
    }

    public function test_an_archived_canvas_takes_no_element(): void
    {
        $this->canvas->update(['archived_at' => now()]);

        $this->assertSame('Dit canvas is gearchiveerd', $this->refusal(fn () => $this->domain()));
        $this->assertSame(0, Element::query()->count());
    }
}
