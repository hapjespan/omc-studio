<?php

namespace Tests\Feature\Refusals;

use App\Models\Owner;
use Tests\Feature\Allocation\AllocationTestCase;

class D2ListRefusalsTest extends AllocationTestCase
{
    use RefusalHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefusals();
    }

    public function test_a_list_name_already_in_the_list_is_refused(): void
    {
        Owner::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Afdeling ICT']);
        $before = $this->stored();

        $this->signedIn()->post($this->listPath('owners'), ['name' => '  afdeling ict  '])
            ->assertSessionHasErrors(['name' => 'Naam: deze naam staat al in de lijst']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_deleting_a_list_entry_in_use_without_a_replacement_is_refused(): void
    {
        $record = $this->costRecord();
        $before = $this->stored();

        $this->signedIn()->delete($this->listPath('owners/'.$record->owner_id))
            ->assertSessionHasErrors(['rule' => 'Deze waarde is in gebruik. Kies een andere waarde bij Vervangen door']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_replacement_outside_the_organisation_is_refused(): void
    {
        $record = $this->costRecord();
        $elsewhere = Owner::factory()->create();
        $before = $this->stored();

        foreach ([(string) $elsewhere->id, 'none'] as $replacement) {
            $this->signedIn()->delete($this->listPath('owners/'.$record->owner_id), ['replace_with' => $replacement])
                ->assertSessionHasErrors(['rule' => 'Vervangen door: kies een andere waarde uit deze organisatie']);
        }

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_fiscal_year_outside_the_range_or_already_present_is_refused(): void
    {
        $this->fiscalYear(2026);
        $before = $this->stored();

        foreach ([1999, 2101, 2026] as $year) {
            $this->signedIn()->post('/organisations/'.$this->organisation->id.'/fiscal-years', ['year' => $year])
                ->assertSessionHasErrors(['year' => 'Boekjaar: kies een jaar van 2000 tot en met 2100 dat nog niet bestaat']);
        }

        $this->assertEquals($before, $this->stored());
    }

    public function test_deleting_a_fiscal_year_with_yearly_figures_is_refused(): void
    {
        $this->costRecord();
        $before = $this->stored();

        $this->signedIn()->delete('/fiscal-years/'.$this->fiscalYear(2026)->id)
            ->assertSessionHasErrors(['rule' => 'Dit boekjaar heeft nog jaarwaarden']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_cost_record_code_already_in_the_organisation_is_refused(): void
    {
        $record = $this->costRecord();
        $record->update(['code' => 'LC-001']);
        $before = $this->stored();

        $this->signedIn()->post('/organisations/'.$this->organisation->id.'/cost-records', [
            'code' => 'lc-001',
            'name' => 'Tweede kostenpost',
            'cost_kind' => 'other',
            'owner_id' => $record->owner_id,
        ])->assertSessionHasErrors(['code' => 'Code: deze code bestaat al in deze organisatie']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_cost_record_without_a_name_cost_kind_or_owner_is_refused(): void
    {
        $before = $this->stored();

        $this->signedIn()->post('/organisations/'.$this->organisation->id.'/cost-records', ['code' => 'LC-001'])
            ->assertSessionHasErrors([
                'name' => 'Naam: vul een naam in',
                'cost_kind' => 'Soort kosten: kies een soort',
                'owner_id' => 'Eigenaar: kies een eigenaar',
            ]);

        $this->assertEquals($before, $this->stored());
    }

    public function test_an_owner_outside_the_organisation_is_refused_on_a_cost_record(): void
    {
        $elsewhere = Owner::factory()->create();
        $before = $this->stored();

        $this->signedIn()->post('/organisations/'.$this->organisation->id.'/cost-records', [
            'name' => 'Licentie planning',
            'cost_kind' => 'other',
            'owner_id' => $elsewhere->id,
        ])->assertSessionHasErrors(['owner_id' => 'Eigenaar: kies een eigenaar uit deze organisatie']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_run_and_change_above_one_hundred_percent_together_are_refused(): void
    {
        $record = $this->costRecord();
        $before = $this->stored();

        $this->signedIn()->put($this->figuresPath($record), ['budget' => '12.500,00', 'run' => '70', 'change' => '40'])
            ->assertSessionHasErrors(['rule' => 'Run en change: samen maximaal 100%']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_an_amount_outside_the_range_or_without_a_comma_is_refused(): void
    {
        $record = $this->costRecord();
        $before = $this->stored();

        foreach (['12.50', '-5', '1000000000'] as $amount) {
            $this->signedIn()->put($this->figuresPath($record), ['budget' => $amount])
                ->assertSessionHasErrors(['budget' => 'Budget: vul een bedrag in van € 0,00 tot € 999.999.999,99, met een komma voor de centen']);
        }

        $this->assertEquals($before, $this->stored());
    }

    public function test_yearly_figures_of_a_year_finalised_in_a_canvas_are_refused(): void
    {
        $record = $this->finalisedRecord();
        $before = $this->stored();

        $this->signedIn()->put($this->figuresPath($record), ['budget' => '2.000,00'])
            ->assertSessionHasErrors(['rule' => '2026 is definitief in canvas Bedrijfsmodel. Heropen het jaar eerst']);
        $this->signedIn()->delete($this->figuresPath($record))
            ->assertSessionHasErrors(['rule' => '2026 is definitief in canvas Bedrijfsmodel. Heropen het jaar eerst']);

        $this->assertEquals($before, $this->stored());
    }

    public function test_deleting_a_cost_record_with_figures_in_a_finalised_year_is_refused(): void
    {
        $record = $this->finalisedRecord();
        $before = $this->stored();

        $this->signedIn()->delete('/cost-records/'.$record->id)
            ->assertSessionHasErrors(['rule' => '2026 is definitief in canvas Bedrijfsmodel. Heropen het jaar eerst, of archiveer de kostenpost']);

        $this->assertEquals($before, $this->stored());
    }
}
