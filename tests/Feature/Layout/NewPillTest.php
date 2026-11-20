<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Structure\StructureTestCase;

class NewPillTest extends StructureTestCase
{
    public function test_elements_added_after_the_last_layout_save_are_marked_new(): void
    {
        $old = $this->domain('primary_activities', 'Oud');
        $old->forceFill(['created_at' => CarbonImmutable::parse('2026-01-01 10:00:00')])->save();
        $this->canvas->forceFill(['layout_saved_at' => CarbonImmutable::parse('2026-01-02 10:00:00')])->save();
        $new = $this->domain('primary_activities', 'Nieuw');
        $new->forceFill(['created_at' => CarbonImmutable::parse('2026-01-03 10:00:00')])->save();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$this->canvas->id}/drawing")->assertInertia(fn (Assert $page) => $page
            ->where('newElements', [(string) $new->id]));
    }

    public function test_nothing_is_new_before_the_first_layout_save(): void
    {
        $this->domain();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$this->canvas->id}/drawing")->assertInertia(fn (Assert $page) => $page->where('newElements', []));
    }
}
