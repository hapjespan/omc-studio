<?php

namespace Tests\Feature\Structure;

use App\Models\AuditEntry;
use App\Models\Element;

class UpdateElementTest extends StructureTestCase
{
    public function test_name_and_description_change_with_one_audit_entry_and_a_canvas_touch(): void
    {
        $domain = $this->domain();
        $this->canvas->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $before = AuditEntry::count();

        $this->tree()->update($domain, ['name' => 'Verkoop', 'description' => 'Alles over verkoop']);

        $this->assertSame('Verkoop', $domain->fresh()->name);
        $this->assertSame($before + 1, AuditEntry::count());
        $this->assertTrue($this->canvas->fresh()->updated_at->isToday());
    }

    public function test_an_unchanged_save_writes_nothing(): void
    {
        $domain = $this->domain();
        $before = AuditEntry::count();

        $this->tree()->update($domain, ['name' => 'Inkoop']);

        $this->assertSame($before, AuditEntry::count());
    }

    public function test_the_own_name_is_not_a_clash_but_a_sibling_name_is(): void
    {
        $domain = $this->domain('primary_activities', 'Inkoop');
        $this->domain('primary_activities', 'Verkoop');

        $this->tree()->update($domain, ['name' => ' INKOOP ']);

        $message = $this->refusal(fn () => $this->tree()->update($domain, ['name' => 'verkoop']));
        $this->assertSame('Naam: er staat al een element met deze naam op deze plek', $message);
        $this->assertSame('INKOOP', $domain->fresh()->name);
    }

    public function test_a_sign_in_the_name_is_refused(): void
    {
        $domain = $this->domain();

        $this->assertSame('Naam: het teken > is niet toegestaan', $this->refusal(fn () => $this->tree()->update($domain, ['name' => 'A > B'])));
        $this->assertSame('Inkoop', $domain->fresh()->name);
    }

    public function test_a_description_over_two_thousand_characters_is_refused(): void
    {
        $domain = $this->domain();

        $this->tree()->update($domain, ['description' => str_repeat('a', 2000)]);
        $message = $this->refusal(fn () => $this->tree()->update($domain, ['description' => str_repeat('a', 2001)]));

        $this->assertSame('Omschrijving: gebruik hoogstens 2000 tekens', $message);
    }

    public function test_an_icon_is_accepted_in_a_list_area_from_the_bundled_list_only(): void
    {
        $group = $this->domain('actors', 'Klanten');

        $this->tree()->update($group, ['icon' => 'users']);
        $this->assertSame('users', $group->fresh()->icon);

        $this->assertNotNull($this->refusal(fn () => $this->tree()->update($group, ['icon' => 'skull'])));
        $this->assertSame('users', $group->fresh()->icon);
    }

    public function test_an_icon_on_an_activity_area_element_is_refused(): void
    {
        $domain = $this->domain();

        $message = $this->refusal(fn () => $this->tree()->update($domain, ['icon' => 'users']));

        $this->assertSame('Een icoon kan alleen bij elementen in een lijstarea', $message);
        $this->assertNull($domain->fresh()->icon);
    }

    public function test_a_null_icon_clears_it(): void
    {
        $group = $this->domain('actors', 'Klanten');
        $this->tree()->update($group, ['icon' => 'users']);

        $this->tree()->update($group, ['icon' => null]);

        $this->assertNull($group->fresh()->icon);
        $this->assertSame(1, Element::count());
    }

    public function test_the_unknown_allocation_domain_is_refused(): void
    {
        $unknown = Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => $this->area('supporting_activities')->id, 'is_unknown_allocation' => true]);

        $this->assertSame('Onbekende allocatie wordt door het systeem beheerd', $this->refusal(fn () => $this->tree()->update($unknown, ['description' => 'x'])));
    }
}
