<?php

namespace Tests\Feature\Exchange;

use App\Models\Organisation;
use App\Models\User;
use App\Models\YearlyFigure;
use Illuminate\Http\UploadedFile;

class CostRecordCsvExportTest extends ExchangeTestCase
{
    private const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

    private function export(): string
    {
        return $this->get('/organisations/'.$this->organisation->id.'/export/cost-records.csv')->assertOk()->streamedContent();
    }

    public function test_lines_per_year_and_one_empty_line_without_figures(): void
    {
        $record = $this->costRecord('K1', 'Licenties', 123_456);
        YearlyFigure::query()->where('cost_record_id', $record->id)->update(['forecast_cents' => 5, 'run_bp' => 6250, 'change_bp' => 3750, 'fte_hundredths' => 150]);
        $this->costRecord('K2', 'Hosting', null);

        $lines = explode("\n", trim($this->export()));

        $this->assertSame(self::HEADER, $lines[0]);
        $this->assertSame('K1;Licenties;Overig;;Ada;;;2026;1234,56;0,05;62,50;37,50;1,50', $lines[1]);
        $this->assertSame('K2;Hosting;Overig;;Ada;;;;;;;;', $lines[2]);
        $this->assertCount(3, $lines);
    }

    public function test_cost_records_without_code_are_left_out_and_counted(): void
    {
        $this->costRecord('K1');
        $this->costRecord(null, 'Zonder code');
        $this->costRecord(null, 'Nog een');

        $response = $this->get('/organisations/'.$this->organisation->id.'/export/cost-records.csv')->assertOk();

        $response->assertHeader('X-Omitted-Cost-Records', '2');
        $this->assertStringNotContainsString('Zonder code', $response->streamedContent());
    }

    public function test_formula_fields_are_prefixed_with_an_apostrophe(): void
    {
        $record = $this->costRecord('K1', '=SOM(A1)');
        $record->update(['supplier' => '+x']);

        $this->assertStringContainsString("K1;'=SOM(A1);Overig;'+x;Ada", $this->export());
    }

    public function test_the_export_reads_unchanged_on_every_line_when_imported_again(): void
    {
        $this->costRecord('K1', 'Licenties', 123_456);
        $this->costRecord('K2', 'Hosting', null);
        $this->costRecord('K3', 'Met;puntkomma', 5_000);

        $file = UploadedFile::fake()->createWithContent('import.csv', $this->export());
        $report = $this->post('/organisations/'.$this->organisation->id.'/cost-records/import/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(['new' => 0, 'updated' => 0, 'unchanged' => 3, 'errors' => 0], $report->json('totals'));
    }

    public function test_any_signed_in_user_may_export_a_guest_may_not(): void
    {
        $this->costRecord('K1');
        $url = '/organisations/'.$this->organisation->id.'/export/cost-records.csv';

        $this->actingAs(User::factory()->create())->get($url)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($url)->assertRedirect();
    }

    public function test_an_unknown_organisation_is_not_found(): void
    {
        $this->get('/organisations/'.(Organisation::max('id') + 1).'/export/cost-records.csv')->assertNotFound();
    }
}
