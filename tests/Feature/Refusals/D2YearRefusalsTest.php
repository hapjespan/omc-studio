<?php

namespace Tests\Feature\Refusals;

use App\Models\Element;
use Tests\Feature\Allocation\AllocationTestCase;

class D2YearRefusalsTest extends AllocationTestCase
{
    use RefusalHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefusals();
    }

    public function test_a_carry_over_into_a_finalised_year_is_refused(): void
    {
        $record = $this->costRecord();
        $this->allocate($record, 2026, [$this->target()->id => 10_000]);
        $this->fiscalYear(2027);
        $this->finalise(2027);
        $before = $this->stored();

        $this->signedIn()->post('/canvases/'.$this->canvas->id.'/years/2027/carry-over', ['source_year' => 2026, 'with_figures' => true])
            ->assertSessionHasErrors(['rule' => '2027 is definitief in canvas Bedrijfsmodel. Heropen het jaar eerst']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_finalising_with_a_cost_record_in_unknown_allocation_is_refused(): void
    {
        $this->costRecord('Licentie');
        $before = $this->stored();

        $this->signedIn()->postJson($this->finalisePath('finalise'))
            ->assertUnprocessable()->assertJsonPath('errors.rule.0', 'Nog 1 kostenpost in onbekende allocatie: Licentie');

        $this->assertEquals($before, $this->stored());
    }

    public function test_finalising_with_a_failing_closing_check_is_refused(): void
    {
        $element = $this->allocatedTarget();
        Element::whereKey($element->id)->update(['archived_at' => now()]);
        $before = $this->stored();

        $this->signedIn()->postJson($this->finalisePath('finalise'))
            ->assertUnprocessable()->assertJsonPath('errors.rule.0', 'De cijfers sluiten niet; definitief maken kan niet');

        $this->assertEquals($before, $this->stored());
    }

    public function test_finalising_a_year_that_is_already_finalised_is_refused(): void
    {
        $this->allocatedTarget();
        $this->finalise(2026);
        $before = $this->stored();

        $this->signedIn()->postJson($this->finalisePath('finalise'))
            ->assertUnprocessable()->assertJsonPath('errors.rule.0', '2026 is al definitief');

        $this->assertEquals($before, $this->stored());
    }

    public function test_reopening_a_year_that_is_not_finalised_is_refused(): void
    {
        $this->allocatedTarget();
        $before = $this->stored();

        $this->signedIn()->postJson($this->finalisePath('reopen'), ['confirm' => true])
            ->assertUnprocessable()->assertJsonPath('errors.rule.0', '2026 is niet definitief');

        $this->assertEquals($before, $this->stored());
    }
}
