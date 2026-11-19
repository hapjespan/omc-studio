<?php

namespace Tests\Feature\Allocation;

use App\Services\Structure\ElementArchive;
use Inertia\Testing\AssertableInertia as Assert;

class EntryCostsPropsTest extends AllocationTestCase
{
    public function test_entry_props_carry_amount_and_record_count_per_element_for_the_year_with_the_subtree(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $second = $this->activity($domain, 'A2');
        $other = $this->target('Elders');
        $licence = $this->costRecord('Licentie', 2026, 100_000);
        $hosting = $this->costRecord('Hosting', 2026, 20_000);
        $this->allocate($licence, 2026, [$first->id => 3000, $second->id => 2000, $other->id => 5000]);
        $this->allocate($hosting, 2026, [$first->id => 10000]);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->where('year', 2026)
            ->where("costs.{$first->id}", ['cents' => 50_000, 'records' => 2, 'years' => [2026], 'own' => true])
            ->where("costs.{$domain->id}.cents", 70_000)
            ->where("costs.{$domain->id}.records", 2)
            ->where("costs.{$domain->id}.own", false)
            ->missing("costs.{$this->canvas->id}"));
    }

    public function test_an_element_with_lines_only_in_another_year_lists_that_year_without_an_amount_in_the_selected_one(): void
    {
        $element = $this->target();
        $this->fiscalYear(2026);
        $this->allocate($this->costRecord('Licentie', 2025), 2025, [$element->id => 10000]);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->where("costs.{$element->id}", ['cents' => 0, 'records' => 0, 'years' => [2025], 'own' => true]));
    }

    public function test_an_element_without_lines_has_no_costs_entry(): void
    {
        $element = $this->target();
        $this->fiscalYear(2026);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->missing("costs.{$element->id}"));
    }

    public function test_targets_hold_the_cost_carrying_tree_without_unknown_allocation_or_archived_elements(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain, 'A1');
        app(ElementArchive::class)->archive($this->activity($domain, 'Weg'));
        $this->fiscalYear(2026);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry")->assertInertia(fn (Assert $page) => $page
            ->has('targets', 1)
            ->where('targets.0.id', $domain->id)
            ->has('targets.0.children', 1)
            ->where('targets.0.children.0.id', $activity->id));
    }
}
