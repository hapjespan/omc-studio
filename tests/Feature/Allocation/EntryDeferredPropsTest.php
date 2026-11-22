<?php

namespace Tests\Feature\Allocation;

use Inertia\Testing\AssertableInertia as Assert;

class EntryDeferredPropsTest extends AllocationTestCase
{
    public function test_the_first_response_of_entry_carries_neither_the_costs_nor_the_targets(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain, 'A1');
        $this->allocate($this->costRecord('Licentie', 2026, 100_000), 2026, [$activity->id => 10000]);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->has('elements')
            ->where('year', 2026)
            ->missing('costs')
            ->missing('targets'));
    }

    public function test_one_deferred_load_returns_the_costs_and_the_targets_together(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain, 'A1');
        $this->allocate($this->costRecord('Licentie', 2026, 100_000), 2026, [$activity->id => 10000]);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $loaded) => $loaded
                ->where("costs.{$activity->id}.cents", 100_000)
                ->where('targets.0.id', $domain->id)));
    }

    public function test_a_partial_reload_of_other_props_does_not_load_them(): void
    {
        $this->domain();

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('elements', fn (Assert $reloaded) => $reloaded->missing('costs')->missing('targets')));
    }
}
