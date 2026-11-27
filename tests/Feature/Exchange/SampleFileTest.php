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

    public function test_each_sample_holds_the_exact_headers_then_only_data_rows(): void
    {
        foreach (self::HEADERS as $group => $header) {
            $lines = explode("\n", trim($this->sample($group)));

            $this->assertSame($header, $lines[0]);
            $this->assertCount(4, $lines);

            foreach (array_slice($lines, 1) as $line) {
                $this->assertStringStartsNotWith('#', $line);
            }
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

    public function test_the_cost_record_sample_uploads_as_is(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', $this->sample('cost_record'));
        $response = $this->post('/organisations/'.$this->organisation->id.'/cost-records/import/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(0, $response->json('totals.errors'), json_encode($response->json('lines')));
        $this->assertSame([2, 3, 4], array_column($response->json('lines'), 'line'));
    }

    public function test_the_structure_sample_uploads_as_is(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', $this->sample('structure'));
        $response = $this->post('/canvases/'.$this->canvas->id.'/import/structure/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(0, $response->json('totals.errors'), json_encode($response->json('lines')));
        $this->assertSame([2, 3, 4], array_column($response->json('lines'), 'line'));
    }

    public function test_the_allocation_sample_reaches_validation_row_by_row(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', $this->sample('allocation'));
        $response = $this->post('/canvases/'.$this->canvas->id.'/import/allocations/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $this->assertStringContainsString('Regel 2, kostenpost_code', json_encode($response->json('lines')));
    }

    public function test_every_row_reaches_validation_in_every_importer_even_when_it_starts_with_a_hash(): void
    {
        $cost = $this->upload(self::HEADERS['cost_record'], ['#1;Naam;Overig;;Ada;;;;;;;;', '#code: tekst;;;;;;;;;;;;'], '/organisations/'.$this->organisation->id.'/cost-records/import/preview');
        $structure = $this->upload(self::HEADERS['structure'], ['#x;Inkoop;;;;', '#area: tekst;;;;;'], '/canvases/'.$this->canvas->id.'/import/structure/preview');
        $allocation = $this->upload(self::HEADERS['allocation'], ['#1;2026;pad;100', '#kostenpost_code: tekst;;;'], '/canvases/'.$this->canvas->id.'/import/allocations/preview');

        $this->assertSame([2, 3], array_column($cost->json('lines'), 'line'));
        $this->assertSame([2, 3], array_column($structure->json('lines'), 'line'));
        $text = json_encode($allocation->json('lines'));

        $this->assertStringContainsString('Regel 2,', $text);
        $this->assertStringContainsString('Regel 3,', $text);
    }

    public function test_the_three_samples_chain_into_an_error_free_allocation_preview(): void
    {
        $post = fn (string $group, string $url) => $this->post($url, ['file' => UploadedFile::fake()->createWithContent('import.csv', $this->sample($group))], ['Accept' => 'application/json']);

        $post('cost_record', '/organisations/'.$this->organisation->id.'/cost-records/import')->assertOk();
        $post('structure', '/canvases/'.$this->canvas->id.'/import/structure')->assertOk();
        $preview = $post('allocation', '/canvases/'.$this->canvas->id.'/import/allocations/preview')->assertOk();

        $this->assertSame(0, $preview->json('totals.errors'), json_encode($preview->json('lines')));
    }
}
