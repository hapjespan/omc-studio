<?php

namespace Tests\Feature\Refusals;

use Tests\Feature\Allocation\AllocationTestCase;

class D2AllocationRefusalsTest extends AllocationTestCase
{
    use RefusalHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefusals();
    }

    public function test_a_set_that_does_not_add_up_to_one_hundred_percent_is_refused(): void
    {
        $record = $this->costRecord();
        $target = $this->target();
        $this->allocate($record, 2026, [$target->id => 10_000]);
        $before = $this->stored();

        $this->signedIn()->put($this->allocationPath($record), ['lines' => [['element_id' => $target->id, 'basis_points' => 9999]]])
            ->assertSessionHasErrors(['rule' => 'De verdeling moet precies 100,00% zijn; nu 99,99%']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_line_outside_the_percentage_range_is_refused(): void
    {
        $record = $this->costRecord();
        $first = $this->target('Offreren');
        $second = $this->target('Bestellen');
        $before = $this->stored();

        foreach ([0, -5, 10_001, '33,5'] as $basisPoints) {
            $this->signedIn()->put($this->allocationPath($record), ['lines' => [
                ['element_id' => $first->id, 'basis_points' => $basisPoints],
                ['element_id' => $second->id, 'basis_points' => 10_000],
            ]])->assertSessionHasErrors(['rule' => 'Regel Offreren: kies een percentage van 0,01% tot 100,00%']);
        }

        $this->assertEquals($before, $this->stored());
    }

    public function test_the_same_target_twice_in_one_set_is_refused(): void
    {
        $record = $this->costRecord();
        $target = $this->target('Offreren');
        $before = $this->stored();

        $this->signedIn()->put($this->allocationPath($record), ['lines' => [
            ['element_id' => $target->id, 'basis_points' => 5000],
            ['element_id' => $target->id, 'basis_points' => 5000],
        ]])->assertSessionHasErrors(['rule' => 'Doel Offreren staat twee keer in de verdeling']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_target_that_cannot_carry_costs_is_refused(): void
    {
        $record = $this->costRecord();
        $listed = $this->listElement('Leveranciers');
        $before = $this->stored();

        $this->signedIn()->put($this->allocationPath($record), ['lines' => [['element_id' => $listed->id, 'basis_points' => 10_000]]])
            ->assertSessionHasErrors(['rule' => 'Doel Leveranciers kan geen kosten dragen']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_set_for_a_cost_record_without_yearly_figures_is_refused(): void
    {
        $record = $this->costRecord('Zonder jaarwaarden', null);
        $this->fiscalYear(2026);
        $target = $this->target();
        $before = $this->stored();

        $this->signedIn()->put($this->allocationPath($record), ['lines' => [['element_id' => $target->id, 'basis_points' => 10_000]]])
            ->assertSessionHasErrors(['rule' => 'Geen jaarwaarden in 2026 voor deze kostenpost']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_set_on_an_archived_canvas_is_refused(): void
    {
        $record = $this->costRecord();
        $target = $this->target();
        $this->canvas->update(['archived_at' => now()]);
        $before = $this->stored();

        $this->signedIn()->put($this->allocationPath($record), ['lines' => [['element_id' => $target->id, 'basis_points' => 10_000]]])
            ->assertSessionHasErrors(['rule' => 'Dit canvas is gearchiveerd']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_set_in_a_finalised_year_is_refused(): void
    {
        $record = $this->finalisedRecord();
        $target = $this->target();
        $before = $this->stored();

        $this->signedIn()->put($this->allocationPath($record), ['lines' => [['element_id' => $target->id, 'basis_points' => 10_000]]])
            ->assertSessionHasErrors(['rule' => '2026 is definitief. Heropen het jaar eerst']);
        $this->signedIn()->delete($this->allocationPath($record))
            ->assertSessionHasErrors(['rule' => '2026 is definitief. Heropen het jaar eerst']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_clearing_a_cost_record_without_a_set_is_refused(): void
    {
        $record = $this->costRecord();
        $before = $this->stored();

        $this->signedIn()->delete($this->allocationPath($record))
            ->assertSessionHasErrors(['rule' => 'Deze kostenpost heeft geen verdeling in 2026']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_removing_an_element_that_carries_costs_asks_for_a_redistribution_first(): void
    {
        $element = $this->allocatedTarget();
        $before = $this->stored();

        $this->signedIn()->delete('/canvases/'.$this->canvas->id.'/elements/'.$element->id)
            ->assertSessionHasErrors(['rule' => 'Op dit element staan nog kosten. Herverdeel ze eerst']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_redistribution_without_a_choice_per_year_is_refused(): void
    {
        $element = $this->allocatedTarget();
        $before = $this->stored();

        $this->signedIn()->post($this->redistributionPath($element), ['choices' => []])
            ->assertSessionHasErrors(['rule' => '2026: kies wat er met de verdeling gebeurt']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_redistribution_target_inside_the_removed_element_is_refused(): void
    {
        $element = $this->allocatedTarget();
        $before = $this->stored();

        $this->signedIn()->post($this->redistributionPath($element), ['choices' => [2026 => ['mode' => 'move', 'target' => (string) $element->id]]])
            ->assertSessionHasErrors(['rule' => 'Kies een doel buiten het element dat je verwijdert']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_redistribution_target_that_cannot_carry_costs_is_refused(): void
    {
        $element = $this->allocatedTarget();
        $listed = $this->listElement('Leveranciers');
        $before = $this->stored();

        $this->signedIn()->post($this->redistributionPath($element), ['choices' => [2026 => ['mode' => 'move', 'target' => (string) $listed->id]]])
            ->assertSessionHasErrors(['rule' => 'Doel Leveranciers kan geen kosten dragen']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_redistribution_in_a_finalised_year_is_refused(): void
    {
        $element = $this->allocatedTarget();
        $this->finalise(2026);
        $before = $this->stored();

        $this->signedIn()->getJson($this->redistributionPath($element))
            ->assertUnprocessable()->assertJsonPath('errors.rule.0', '2026 is definitief. Heropen het jaar eerst');

        $this->assertEquals($before, $this->stored());
    }
}
