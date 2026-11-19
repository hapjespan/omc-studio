<?php

namespace Tests\Feature\Exchange;

use App\Models\AllocationSet;
use App\Models\Element;
use PHPUnit\Framework\Attributes\DataProvider;

class CsvFaultyFilesTest extends ExchangeTestCase
{
    private const PATH = 'Primaire activiteiten > Inkoop > Offreren';

    /** @return array<string, array{string, string, list<string>, int}> */
    public static function faultyFiles(): array
    {
        $allocations = 'kostenpost_code;jaar;doel_pad;percentage';
        $structure = 'area;domein;cluster;activiteit;omschrijving;modaliteit';

        return [
            'allocation unknown code' => ['import/allocations', $allocations, ['NOPE;2026;'.self::PATH.';100'], 1],
            'allocation unknown year' => ['import/allocations', $allocations, ['K1;1999;'.self::PATH.';100'], 1],
            'allocation unknown path' => ['import/allocations', $allocations, ['K1;2026;Primaire activiteiten > Weg;100'], 1],
            'allocation not a target' => ['import/allocations', $allocations, ['K1;2026;Actoren;100'], 1],
            'allocation bad percentage' => ['import/allocations', $allocations, ['K1;2026;'.self::PATH.';abc'], 1],
            'allocation sum not 100' => ['import/allocations', $allocations, ['K1;2026;'.self::PATH.';60'], 1],
            'allocation repeated target' => ['import/allocations', $allocations, ['K1;2026;'.self::PATH.';50', 'K1;2026;'.self::PATH.';50'], 1],
            'allocation extra semicolons' => ['import/allocations', $allocations, ['K1;2026;'.self::PATH.';60;x;y'], 1],
            'structure unknown area' => ['import/structure', $structure, ['Nergens;Nieuw;;;;'], 1],
            'structure missing domain' => ['import/structure', $structure, ['Primaire activiteiten;;;Los;;'], 1],
            'structure extra semicolons' => ['import/structure', $structure, ['Primaire activiteiten;;;Los;;;x;y'], 1],
            'structure modality in list area' => ['import/structure', $structure, ['Actoren;Groep;;;;C'], 1],
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    #[DataProvider('faultyFiles')]
    public function test_each_fault_is_reported_and_nothing_is_written(string $path, string $header, array $lines, int $errors): void
    {
        $this->costRecord('K1');
        $elements = Element::count();
        $url = '/canvases/'.$this->canvas->id.'/'.$path;

        $report = $this->upload($header, $lines, $url.'/preview')->assertOk();

        $this->assertGreaterThanOrEqual($errors, $report->json('totals.errors'));
        $this->assertNotSame([], array_filter($report->json('lines'), fn (array $line) => $line['messages'] !== []));

        $this->upload($header, $lines, $url)->assertStatus(422);

        $this->assertSame($elements, Element::count());
        $this->assertSame(0, AllocationSet::count());
    }
}
