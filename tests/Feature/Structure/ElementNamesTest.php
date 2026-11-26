<?php

namespace Tests\Feature\Structure;

use App\Enums\ElementKind;
use App\Models\Element;

class ElementNamesTest extends StructureTestCase
{
    public function test_a_name_equal_ignoring_case_and_padding_is_refused_under_the_same_parent(): void
    {
        $domain = $this->domain();
        $this->tree()->create($this->canvas, $domain->area, ElementKind::Activity, $domain->id, ['name' => 'Werving']);

        $message = $this->refusal(fn () => $this->tree()->create($this->canvas, $domain->area, ElementKind::Activity, $domain->id, ['name' => 'werving ']));

        $this->assertSame('Naam: er staat al een element met deze naam op deze plek', $message);
        $this->assertSame(2, Element::count());
    }

    public function test_the_same_name_is_allowed_under_another_parent(): void
    {
        $first = $this->domain('primary_activities', 'Personeel');
        $second = $this->domain('primary_activities', 'Inkoop');
        $this->tree()->create($this->canvas, $first->area, ElementKind::Activity, $first->id, ['name' => 'Werving']);

        $this->tree()->create($this->canvas, $second->area, ElementKind::Activity, $second->id, ['name' => 'Werving']);

        $this->assertSame(4, Element::count());
    }

    public function test_a_domain_name_is_unique_within_its_area_only(): void
    {
        $this->domain('primary_activities', 'Inkoop');
        $this->domain('supporting_activities', 'Inkoop');

        $message = $this->refusal(fn () => $this->domain('primary_activities', 'INKOOP'));

        $this->assertSame('Naam: er staat al een element met deze naam op deze plek', $message);
    }

    public function test_an_archived_element_does_not_hold_its_name(): void
    {
        $domain = $this->domain();
        $domain->update(['archived_at' => now()]);

        $this->domain();

        $this->assertSame(2, Element::count());
    }

    public function test_a_name_with_the_greater_than_sign_is_refused(): void
    {
        $message = $this->refusal(fn () => $this->domain('primary_activities', 'A > B'));

        $this->assertSame('Naam: het teken > is niet toegestaan', $message);
        $this->assertSame(0, Element::count());
    }

    public function test_a_blank_name_is_refused(): void
    {
        $this->assertNotNull($this->refusal(fn () => $this->domain('primary_activities', '  ')));
        $this->assertSame(0, Element::count());
    }
}
