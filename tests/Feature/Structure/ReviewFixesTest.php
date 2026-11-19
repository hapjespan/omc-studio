<?php

namespace Tests\Feature\Structure;

use App\Enums\ElementKind;
use App\Models\Element;
use App\Models\User;
use App\Services\Structure\ElementArchive;
use App\Services\Structure\ElementDeletion;

class ReviewFixesTest extends StructureTestCase
{
    public function test_an_archived_parent_is_refused_for_create_and_move(): void
    {
        $domain = $this->domain();
        $other = $this->domain('primary_activities', 'Anders');
        $activity = $this->activity($other);
        app(ElementArchive::class)->archive($domain);

        $this->assertSame('Een activiteit hoort onder een domein of een cluster', $this->refusal(fn () => $this->tree()->create($this->canvas, $domain->area, ElementKind::Activity, $domain->id, ['name' => 'X'])));
        $this->assertSame('Een activiteit hoort onder een domein of een cluster', $this->refusal(fn () => $this->tree()->move($activity, $domain)));
        $this->assertSame($other->id, $activity->fresh()->parent_id);
    }

    public function test_an_archived_element_cannot_be_moved(): void
    {
        $domain = $this->domain();
        $target = $this->domain('primary_activities', 'Doel');
        $activity = $this->activity($domain);
        app(ElementArchive::class)->archive($activity);

        $this->assertSame('Een gearchiveerd element kan niet worden verplaatst', $this->refusal(fn () => $this->tree()->move($activity, $target)));
    }

    public function test_a_name_over_150_characters_is_refused_on_create(): void
    {
        $message = $this->refusal(fn () => $this->domain('primary_activities', str_repeat('a', 151)));

        $this->assertSame('Naam: gebruik hoogstens 150 tekens', $message);
        $this->assertSame(0, Element::count());
    }

    public function test_a_description_over_2000_characters_is_refused_on_create(): void
    {
        $message = $this->refusal(fn () => $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Domain, null, ['name' => 'X', 'description' => str_repeat('a', 2001)]));

        $this->assertSame('Omschrijving: gebruik hoogstens 2000 tekens', $message);
        $this->assertSame(0, Element::count());
    }

    public function test_an_activity_may_be_lifted_when_it_shares_the_name_of_its_own_cluster(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain, 'Peuters');
        $activity = $this->activity($cluster, 'Peuters');

        app(ElementDeletion::class)->delete($cluster, User::factory()->administrator()->create(), 'lift');

        $this->assertSame($domain->id, $activity->fresh()->parent_id);
    }
}
