<?php

namespace Tests\Feature\Refusals;

use Illuminate\Http\UploadedFile;
use Tests\Feature\Allocation\AllocationTestCase;

class D2ImportRefusalsTest extends AllocationTestCase
{
    use RefusalHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefusals();
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
}
