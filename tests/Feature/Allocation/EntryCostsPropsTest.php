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
            ->where("costs.{$first->id}.cents", 50_000)
            ->where("costs.{$first->id}.records", 2)
            ->where("costs.{$first->id}.years", [2026])
            ->where("costs.{$first->id}.own", true)
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
            ->where("costs.{$element->id}.cents", 0)
            ->where("costs.{$element->id}.records", 0)
            ->where("costs.{$element->id}.years", [2025]));
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

    public function test_amounts_follow_the_calculation_module_largest_remainder(): void
    {
        $domain = $this->domain();
        [$a, $b, $c] = [$this->activity($domain, 'A'), $this->activity($domain, 'B'), $this->activity($domain, 'C')];
        $this->allocate($this->costRecord('Drie', 2026, 100), 2026, [$a->id => 3333, $b->id => 3333, $c->id => 3334]);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->where("costs.{$a->id}.cents", 33)
            ->where("costs.{$b->id}.cents", 33)
            ->where("costs.{$c->id}.cents", 34)
            ->where("costs.{$domain->id}.cents", 100));
    }

    public function test_a_domain_holding_both_halves_of_101_cents_shows_101_not_102(): void
    {
        $domain = $this->domain();
        [$a, $b] = [$this->activity($domain, 'A'), $this->activity($domain, 'B')];
        $this->allocate($this->costRecord('Twee', 2026, 101), 2026, [$a->id => 5000, $b->id => 5000]);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->where("costs.{$a->id}.cents", 51)
            ->where("costs.{$b->id}.cents", 50)
            ->where("costs.{$domain->id}.cents", 101));
    }

    public function test_a_set_whose_cost_record_has_no_yearly_figure_for_the_year_is_not_counted(): void
    {
        $element = $this->target();
        $this->fiscalYear(2026);
        $this->allocate($this->costRecord('Zonder cijfers', null), 2026, [$element->id => 10000]);

        $this->actingAs($this->admin())->get("/canvases/{$this->canvas->id}/entry?year=2026")->assertInertia(fn (Assert $page) => $page
            ->where("costs.{$element->id}.records", 0)
            ->where("costs.{$element->id}.cents", 0)
            ->where("costs.{$element->id}.years", [2026]));
    }
}
