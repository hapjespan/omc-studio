<?php

namespace Tests\Feature\Exchange;

use App\Models\AllocationSet;
use App\Models\Element;
use App\Rules\CsvUploadRule;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;

class CsvUploadRefusalTest extends ExchangeTestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function importers(): array
    {
        $allocations = 'kostenpost_code;jaar;doel_pad;percentage';
        $structure = 'area;domein;cluster;activiteit;omschrijving;modaliteit';

        return [
            'allocations preview' => ['import/allocations/preview', $allocations, 'K1;2026;Primaire activiteiten > Inkoop > Offreren;100'],
            'allocations commit' => ['import/allocations', $allocations, 'K1;2026;Primaire activiteiten > Inkoop > Offreren;100'],
            'structure preview' => ['import/structure/preview', $structure, 'Primaire activiteiten;Nieuw;;;;'],
            'structure commit' => ['import/structure', $structure, 'Primaire activiteiten;Nieuw;;;;'],
        ];
    }

    private function refuse(string $path, UploadedFile $file): void
    {
        $elements = Element::count();
        $sets = AllocationSet::count();

        $response = $this->post('/canvases/'.$this->canvas->id.'/'.$path, ['file' => $file], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertStringStartsWith('Bestand: kies een CSV-bestand van maximaal 5 MB met de kolommen ', $response->json('errors.file.0'));
        $this->assertNull($response->json('lines'));
        $this->assertSame($elements, Element::count());
        $this->assertSame($sets, AllocationSet::count());
    }

    #[DataProvider('importers')]
    public function test_a_file_over_five_megabytes_is_refused(string $path, string $header, string $line): void
    {
        $this->costRecord('K1');
        $content = $header."\n".str_repeat($line."\n", intdiv(CsvUploadRule::MAXIMUM_BYTES, strlen($line) + 1) + 10);

        $this->refuse($path, UploadedFile::fake()->createWithContent('import.csv', $content));
    }

    #[DataProvider('importers')]
    public function test_a_binary_file_renamed_csv_is_refused(string $path, string $header, string $line): void
    {
        $this->refuse($path, UploadedFile::fake()->createWithContent('import.csv', $header."\n\x00\x01\xff\xfe binary"));
    }

    #[DataProvider('importers')]
    public function test_a_wrong_header_is_refused(string $path, string $header, string $line): void
    {
        $this->refuse($path, UploadedFile::fake()->createWithContent('import.csv', "naam;iets\n".$line."\n"));
    }
}
