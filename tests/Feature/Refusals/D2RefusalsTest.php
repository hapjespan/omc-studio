<?php

namespace Tests\Feature\Refusals;

use App\Models\CostRecord;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Allocation\AllocationTestCase;

class D2RefusalsTest extends AllocationTestCase
{
    private const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

    private const TABLES = [
        'cost_records',
        'yearly_figures',
        'allocation_sets',
        'allocation_lines',
        'fiscal_years',
        'owners',
        'applications',
        'finalised_years',
        'elements',
    ];

    private Organisation $organisation;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canvas->update(['name' => 'Bedrijfsmodel']);
        $this->organisation = $this->canvas->organisation()->firstOrFail();
        $this->actor = $this->admin();
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

    public function test_an_imported_line_without_a_code_is_refused(): void
    {
        $this->assertImportMessage([';Licentie;Project;;Ada;;;;;;;;'], 'Regel 2: code ontbreekt; bij importeren is een code verplicht');
    }

    public function test_an_imported_line_with_an_unknown_cost_kind_is_refused(): void
    {
        $this->assertImportMessage(['K1;Licentie;Onbekend;;Ada;;;;;;;;'], "Regel 2, soort: 'Onbekend' is geen soort. Kies Leverancierscontract, Personeel, Project of Overig");
    }

    public function test_an_imported_line_with_a_year_outside_the_fiscal_years_is_refused(): void
    {
        $this->fiscalYear(2026);

        $this->assertImportMessage(['K1;Licentie;Project;;Ada;;;2030;1.000,00;;;;'], "Regel 2, jaar: '2030' is geen boekjaar van deze organisatie. Voeg het boekjaar eerst toe");
    }

    public function test_an_imported_amount_with_a_point_is_refused(): void
    {
        $this->fiscalYear(2026);

        $this->assertImportMessage(['K1;Licentie;Project;;Ada;;;2026;12.50;;;;'], "Regel 2, budget: '12.50' is geen bedrag. Gebruik een komma voor de centen, bijvoorbeeld 12,50");
    }

    public function test_an_imported_line_with_a_year_and_no_budget_is_refused(): void
    {
        $this->fiscalYear(2026);

        $this->assertImportMessage(['K1;Licentie;Project;;Ada;;;2026;;;;;'], 'Regel 2, budget: budget ontbreekt. Vul een bedrag in');
    }

    public function test_an_imported_run_and_change_above_one_hundred_are_refused(): void
    {
        $this->fiscalYear(2026);

        $this->assertImportMessage(['K1;Licentie;Project;;Ada;;;2026;1.000,00;;70;40;'], 'Regel 2, change_pct: run_pct plus change_pct is meer dan 100. Verlaag een van beide');
    }

    public function test_two_imported_lines_with_the_same_code_and_year_are_refused(): void
    {
        $this->fiscalYear(2026);

        $this->assertImportMessage([
            'K1;Licentie;Project;;Ada;;;2026;1.000,00;;;;',
            'K1;Licentie;Project;;Ada;;;2026;2.000,00;;;;',
        ], 'Regel 3: code K1 en jaar 2026 staan ook op regel 2. Laat een van beide regels weg', 1);
    }

    public function test_two_imported_lines_with_the_same_code_and_different_fields_are_refused(): void
    {
        $this->assertImportMessage([
            'K1;Licentie;Project;;Ada;;;;;;;;',
            'K1;Hosting;Project;;Ada;;;;;;;;',
        ], 'Regel 3: code K1 heeft andere kostenpostgegevens dan regel 2. Maak de velden gelijk', 1);
    }

    public function test_an_imported_line_for_a_finalised_year_is_refused(): void
    {
        $this->finalisedRecord();

        $this->assertImportMessage(['K1;Licentie;Project;;Ada;;;2026;1.000,00;;;;'], 'Regel 2: 2026 is definitief in canvas Bedrijfsmodel');
    }

    public function test_importing_a_file_with_errors_is_refused(): void
    {
        $before = $this->stored();

        $this->commit([';Licentie;Project;;Ada;;;;;;;;'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Importeren is niet gelukt; het bestand bevat fouten. Er is niets gewijzigd');

        $this->assertEquals($before, $this->stored());
    }

    public function test_a_file_that_is_not_a_cost_record_csv_is_refused(): void
    {
        $before = $this->stored();
        $file = UploadedFile::fake()->createWithContent('import.csv', "code;naam\nK1;Licentie\n");

        $this->signedIn()->post('/organisations/'.$this->organisation->id.'/cost-records/import/preview', ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Bestand: kies een CSV-bestand van maximaal 5 MB met de kolommen '.self::HEADER);

        $this->assertEquals($before, $this->stored());
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

    private function signedIn(): static
    {
        return $this->actingAs($this->actor);
    }

    private function listPath(string $suffix): string
    {
        return '/admin/organisations/'.$this->organisation->id.'/'.$suffix;
    }

    private function figuresPath(CostRecord $record, int $year = 2026): string
    {
        return '/cost-records/'.$record->id.'/years/'.$year;
    }

    private function allocationPath(CostRecord $record, int $year = 2026): string
    {
        return '/canvases/'.$this->canvas->id.'/years/'.$year.'/cost-records/'.$record->id.'/allocation';
    }

    private function redistributionPath(Element $element): string
    {
        return '/canvases/'.$this->canvas->id.'/elements/'.$element->id.'/redistribution';
    }

    private function finalisePath(string $action, int $year = 2026): string
    {
        return '/canvases/'.$this->canvas->id.'/years/'.$year.'/'.$action;
    }

    private function finalisedRecord(): CostRecord
    {
        $record = $this->costRecord();
        $this->allocate($record, 2026, [$this->target('Toewijzing')->id => 10_000]);
        $this->finalise(2026);

        return $record;
    }

    private function allocatedTarget(): Element
    {
        $target = $this->target('Offreren');
        $this->allocate($this->costRecord(), 2026, [$target->id => 10_000]);

        return $target;
    }

    private function listElement(string $name): Element
    {
        return Element::factory()->create([
            'canvas_id' => $this->canvas->id,
            'area_id' => $this->area('actors')->id,
            'name' => $name,
        ]);
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<Response>
     */
    private function commit(array $lines): TestResponse
    {
        return $this->send('import', $lines);
    }

    /** @param list<string> $lines */
    private function assertImportMessage(array $lines, string $message, int $index = 0): void
    {
        $before = $this->stored();

        $this->send('import/preview', $lines)->assertOk()
            ->assertJsonPath('lines.'.$index.'.status', 'error')
            ->assertJsonPath('lines.'.$index.'.messages.0', $message);

        $this->assertEquals($before, $this->stored());
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<Response>
     */
    private function send(string $suffix, array $lines): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', implode("\n", [self::HEADER, ...$lines])."\n");

        return $this->signedIn()->post('/organisations/'.$this->organisation->id.'/cost-records/'.$suffix, ['file' => $file], ['Accept' => 'application/json']);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        $rows = [];

        foreach (self::TABLES as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();
        }

        return $rows;
    }
}
