<?php

namespace Tests\Feature\Exchange;

use Illuminate\Http\UploadedFile;

class SampleFileTest extends ExchangeTestCase
{
    private const HEADERS = [
        'cost_record' => 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte',
        'structure' => 'area;domein;cluster;activiteit;omschrijving;modaliteit',
        'allocation' => 'kostenpost_code;jaar;doel_pad;percentage',
    ];

    private function sample(string $group): string
    {
        return $this->get('/samples/'.$group.'.csv')->assertOk()->streamedContent();
    }

    public function test_each_sample_starts_with_the_exact_headers_then_one_explanation_line_per_column(): void
    {
        foreach (self::HEADERS as $group => $header) {
            $lines = explode("\n", trim($this->sample($group)));
            $columns = count(explode(';', $header));

            $this->assertSame($header, $lines[0]);

            foreach (array_slice($lines, 1, $columns) as $index => $line) {
                $this->assertStringStartsWith('#'.explode(';', $header)[$index].':', $line);
            }

            $this->assertStringStartsNotWith('#', $lines[$columns + 1]);
        }
    }

    public function test_the_samples_carry_no_client_name(): void
    {
        foreach (array_keys(self::HEADERS) as $group) {
            $this->assertStringNotContainsStringIgnoringCase('indicia', $this->sample($group));
        }
    }

    public function test_an_unknown_sample_and_a_guest_are_refused(): void
    {
        $this->get('/samples/other.csv')->assertNotFound();
        auth()->logout();
        $this->get('/samples/cost_record.csv')->assertRedirect();
    }

    public function test_the_cost_record_sample_uploads_as_is_without_explanation_lines_reaching_validation(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', $this->sample('cost_record'));
        $response = $this->post('/organisations/'.$this->organisation->id.'/cost-records/import/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $lines = array_column($response->json('lines'), 'line');

        $this->assertSame(0, $response->json('totals.errors'), json_encode($response->json('lines')));
        $this->assertSame(3, $response->json('totals.new'));
        $this->assertNotContains(2, $lines);
    }

    public function test_the_structure_sample_uploads_as_is(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', $this->sample('structure'));
        $response = $this->post('/canvases/'.$this->canvas->id.'/import/structure/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(0, $response->json('totals.errors'), json_encode($response->json('lines')));
        $this->assertCount(3, $response->json('lines'));
    }

    public function test_the_allocation_sample_reports_only_real_rows(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', $this->sample('allocation'));
        $response = $this->post('/canvases/'.$this->canvas->id.'/import/allocations/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $text = json_encode($response->json('lines'));

        $this->assertStringContainsString('Regel 6, kostenpost_code', $text);
        $this->assertStringNotContainsString('Regel 2,', $text);
        $this->assertStringNotContainsString('Regel 5,', $text);
    }

    public function test_a_real_row_starting_with_a_hash_still_reaches_validation_in_every_importer(): void
    {
        $cost = $this->upload(self::HEADERS['cost_record'], ['#1;Naam;Overig;;Ada;;;;;;;;'], '/organisations/'.$this->organisation->id.'/cost-records/import/preview');
        $structure = $this->upload(self::HEADERS['structure'], ['#x;Inkoop;;;;'], '/canvases/'.$this->canvas->id.'/import/structure/preview');
        $allocation = $this->upload(self::HEADERS['allocation'], ['#1;2026;pad;100'], '/canvases/'.$this->canvas->id.'/import/allocations/preview');

        $this->assertSame(2, $cost->json('lines.0.line'));
        $this->assertSame(2, $structure->json('lines.0.line'));
        $this->assertNotEmpty($allocation->json('lines'));
    }

    public function test_explanation_lines_never_reach_validation_in_any_importer(): void
    {
        $this->costRecord('K1');
        $cost = $this->upload(self::HEADERS['cost_record'], ['#code: uitleg', 'K1;Naam;Overig;;Ada;;;;;;;;'], '/organisations/'.$this->organisation->id.'/cost-records/import/preview');
        $structure = $this->upload(self::HEADERS['structure'], ['#area: uitleg', 'Primaire activiteiten;Inkoop;;;;'], '/canvases/'.$this->canvas->id.'/import/structure/preview');
        $allocation = $this->upload(self::HEADERS['allocation'], ['#kostenpost_code: uitleg', 'K1;2026;Primaire activiteiten > Inkoop;100'], '/canvases/'.$this->canvas->id.'/import/allocations/preview');

        $this->assertSame([3], array_column($cost->json('lines'), 'line'));
        $this->assertSame([3], array_column($structure->json('lines'), 'line'));
        $this->assertSame(0, $cost->json('totals.errors'));
        $this->assertSame(0, $structure->json('totals.errors'));
        $this->assertSame(0, $allocation->json('totals.errors'), json_encode($allocation->json('lines')));
    }
}
