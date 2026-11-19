<?php

namespace Tests\Feature\Exchange;

use App\Models\Application;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CostRecordImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
        $this->actingAs(User::factory()->administrator()->create());
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<JsonResponse>
     */
    private function preview(array $lines, ?Organisation $organisation = null): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', implode("\n", [self::HEADER, ...$lines])."\n");

        return $this->post('/organisations/'.($organisation ?? $this->organisation)->id.'/cost-records/import/preview', ['file' => $file], ['Accept' => 'application/json']);
    }

    /**
     * @param  TestResponse<JsonResponse>  $response
     * @return list<string>
     */
    private function messages(TestResponse $response, int $index = 0): array
    {
        return $response->json("lines.$index.messages");
    }

    public function test_new_changed_and_unchanged_lines_are_classified_with_totals(): void
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'code' => 'K1', 'name' => 'Licenties', 'cost_kind' => 'supplier_contract']);
        $record->owner->update(['name' => 'Ada']);
        YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->organisation->fiscalYears()->first()->id, 'budget_cents' => 1000]);
        $same = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'code' => 'K2', 'name' => 'Hosting', 'cost_kind' => 'other']);
        $same->owner->update(['name' => 'Bo']);

        $response = $this->preview([
            'K1;Licenties;Leverancierscontract;;Ada;;;2026;12,50;;;;',
            'K2;Hosting;overig;;Bo;;;;;;;;',
            'K3;Nieuw;Project;;Ada;;;2026;1.000,00;;60;30;1,5',
        ])->assertOk();

        $response->assertJsonPath('lines.0.line', 2)->assertJsonPath('lines.0.status', 'updated')
            ->assertJsonPath('lines.1.status', 'unchanged')
            ->assertJsonPath('lines.2.status', 'new')
            ->assertJsonPath('totals', ['new' => 1, 'updated' => 1, 'unchanged' => 1, 'errors' => 0]);
    }

    public function test_over_long_text_fields_are_reported_per_column(): void
    {
        $long = fn (int $length) => str_repeat('a', $length);

        $response = $this->preview([
            $long(51).';'.$long(151).';Project;'.$long(151).';'.$long(151).';'.$long(51).';'.$long(151).';;;;;;',
            $long(50).';'.$long(150).';Project;'.$long(150).';'.$long(150).';'.$long(50).';'.$long(150).';;;;;;',
        ])->assertOk();

        $this->assertSame([
            'Regel 2, code: gebruik hoogstens 50 tekens',
            'Regel 2, naam: gebruik hoogstens 150 tekens',
            'Regel 2, eigenaar: gebruik hoogstens 150 tekens',
            'Regel 2, applicatie: gebruik hoogstens 150 tekens',
            'Regel 2, leverancier: gebruik hoogstens 150 tekens',
            'Regel 2, kostenplaats: gebruik hoogstens 50 tekens',
        ], $this->messages($response));
        $this->assertSame([], $this->messages($response, 1));
    }

    public function test_a_missing_code_is_reported(): void
    {
        $response = $this->preview(['K1;A;Project;;Ada;;;;;;;;', ';B;Project;;Ada;;;;;;;;']);

        $this->assertSame(['Regel 3: code ontbreekt; bij importeren is een code verplicht'], $this->messages($response, 1));
        $response->assertJsonPath('lines.1.status', 'error')->assertJsonPath('totals.errors', 1);
    }

    public function test_an_amount_with_a_point_is_reported_with_the_repair(): void
    {
        $response = $this->preview(['K1;A;Project;;Ada;;;2026;12.50;;;;']);

        $this->assertSame(["Regel 2, budget: '12.50' is geen bedrag. Gebruik een komma voor de centen, bijvoorbeeld 12,50"], $this->messages($response));
    }

    public function test_kind_year_and_percentages_are_checked(): void
    {
        $response = $this->preview([
            'K1;A;Onbekend;;Ada;;;;;;;;',
            'K2;A;PROJECT;;Ada;;;2030;1,00;;;;',
            'K3;A;Project;;Ada;;;2026;1,00;;70;40;',
        ]);

        $this->assertStringStartsWith("Regel 2, soort: 'Onbekend'", $this->messages($response, 0)[0]);
        $response->assertJsonPath('lines.1.messages.0', "Regel 3, jaar: '2030' is geen boekjaar van deze organisatie. Voeg het boekjaar eerst toe");
        $this->assertStringStartsWith('Regel 4, change_pct: ', $this->messages($response, 2)[0]);
        $response->assertJsonPath('totals.errors', 3);
    }

    public function test_same_code_and_year_twice_and_diverging_record_fields_are_reported(): void
    {
        $response = $this->preview([
            'K1;A;Project;;Ada;;;2026;1,00;;;;',
            'k1;A;Project;;Ada;;;2026;2,00;;;;',
            'K1;Andere;Project;;Ada;;;;;;;;',
        ]);

        $this->assertSame(['Regel 3: code k1 en jaar 2026 staan ook op regel 2. Laat een van beide regels weg'], $this->messages($response, 1));
        $this->assertSame(['Regel 4: code K1 heeft andere kostenpostgegevens dan regel 2. Maak de velden gelijk'], $this->messages($response, 2));
    }

    public function test_year_and_budget_go_together(): void
    {
        $response = $this->preview([
            'K1;A;Project;;Ada;;;2026;;;;;',
            'K2;A;Project;;Ada;;;;5,00;;;;',
            'K3;A;Project;;Ada;;;;;;;;',
        ]);

        $this->assertSame(['Regel 2, budget: budget ontbreekt. Vul een bedrag in'], $this->messages($response, 0));
        $this->assertSame(['Regel 3, jaar: jaar ontbreekt. Vul een jaar in of laat de jaarwaarden leeg'], $this->messages($response, 1));
        $response->assertJsonPath('lines.2.status', 'new');
    }

    public function test_a_non_administrator_is_refused_and_nothing_is_written(): void
    {
        $this->preview(['K1;A;Project;;Ada;;;;;;;;'])->assertOk();
        $this->assertSame(0, CostRecord::count());

        $this->actingAs(User::factory()->create());
        $this->preview(['K1;A;Project;;Ada;;;;;;;;'])->assertForbidden();
    }

    public function test_a_changing_line_for_a_year_finalised_in_a_canvas_is_reported_but_unchanged_figures_are_not(): void
    {
        $year = $this->organisation->fiscalYears()->first();
        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Strategie']);
        FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => $year->id]);
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'code' => 'K1', 'name' => 'A', 'cost_kind' => 'project']);
        $record->owner->update(['name' => 'Ada']);
        YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 500]);

        $other = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'code' => 'K3', 'name' => 'C', 'cost_kind' => 'project', 'owner_id' => $record->owner_id]);
        YearlyFigure::factory()->create(['cost_record_id' => $other->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 100]);

        $response = $this->preview(['K1;A;Project;;Ada;;;2026;5,00;;;;', 'K3;C;Project;;Ada;;;2026;6,00;;;;', 'K2;B;Project;;Ada;;;2026;1,00;;;;']);

        $response->assertJsonPath('lines.0.status', 'unchanged');
        $this->assertSame(['Regel 3: 2026 is definitief in canvas Strategie'], $this->messages($response, 1));
        $this->assertSame(['Regel 4: 2026 is definitief in canvas Strategie'], $this->messages($response, 2));
    }

    public function test_new_owners_and_applications_are_listed_and_nothing_is_written(): void
    {
        $existing = CostRecord::factory()->create(['organisation_id' => $this->organisation->id]);
        $existing->owner->update(['name' => 'Ada']);

        $response = $this->preview([
            'K1;A;Project;;ada;;Nieuwe app;;;;;;',
            'K2;B;Project;;Bo;;nieuwe APP;;;;;;',
            ';C;Project;;Cy;;;;;;;;',
        ]);

        $response->assertJsonPath('creates', ['owners' => ['Bo'], 'applications' => ['Nieuwe app']]);
        $this->assertSame(1, CostRecord::count());
        $this->assertSame(1, Owner::count());
        $this->assertSame(0, Application::count());
        $this->assertSame(0, YearlyFigure::count());
    }

    public function test_the_import_page_renders_for_an_administrator(): void
    {
        $this->get('/organisations/'.$this->organisation->id.'/cost-records/import')
            ->assertInertia(fn (Assert $page) => $page->component('Finance/CostRecordImport')->where('organisation.id', $this->organisation->id)->has('columns', 13));
    }

    public function test_a_bad_header_is_refused_with_the_file_message(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', "a;b\n1;2\n");

        $this->post('/organisations/'.$this->organisation->id.'/cost-records/import/preview', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Bestand: kies een CSV-bestand van maximaal 5 MB met de kolommen '.self::HEADER);
    }
}
