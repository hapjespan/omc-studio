<?php

namespace Tests\Unit\Exchange;

use App\Services\Exchange\Csv\CsvReader;
use App\Services\Exchange\Csv\CsvWriter;
use Tests\TestCase;

class CsvWriterTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        $this->paths = [];

        parent::tearDown();
    }

    private function path(): string
    {
        $path = storage_path('app/'.uniqid('csv-writer-').'.csv');
        $this->paths[] = $path;

        return $path;
    }

    /**
     * @param  list<list<string|int|null>>  $lines
     */
    private function written(array $lines): string
    {
        $handle = fopen('php://temp', 'w+b');
        $writer = new CsvWriter($handle);

        foreach ($lines as $fields) {
            $writer->write($fields);
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    public function test_it_prefixes_a_field_a_spreadsheet_would_read_as_a_formula(): void
    {
        $this->assertSame("'=SOM(A1)", CsvWriter::field('=SOM(A1)'));
        $this->assertSame("'+x", CsvWriter::field('+x'));
        $this->assertSame("'-1", CsvWriter::field('-1'));
        $this->assertSame("'@a", CsvWriter::field('@a'));
        $this->assertSame("'\tx", CsvWriter::field("\tx"));
        $this->assertSame("\"'\rx\"", CsvWriter::field("\rx"));
    }

    public function test_it_quotes_only_a_field_with_a_semicolon_quote_or_line_break(): void
    {
        $this->assertSame('Hosting', CsvWriter::field('Hosting'));
        $this->assertSame('', CsvWriter::field(''));
        $this->assertSame('"Hosting; met korting"', CsvWriter::field('Hosting; met korting'));
        $this->assertSame('"een ""quote"""', CsvWriter::field('een "quote"'));
        $this->assertSame("\"twee\nregels\"", CsvWriter::field("twee\nregels"));
    }

    public function test_it_writes_a_line_with_null_as_empty_and_integers_without_a_prefix(): void
    {
        $this->assertSame(
            "code;naam\nLC-001;;-5;\"a;b\"\n",
            $this->written([['code', 'naam'], ['LC-001', null, -5, 'a;b']]),
        );
    }

    public function test_it_writes_utf_eight_without_a_byte_order_mark(): void
    {
        $content = $this->written([['Beheer & onderhoud']]);

        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $content);
        $this->assertSame("Beheer & onderhoud\n", $content);
    }

    public function test_it_formats_hundredths_with_a_decimal_comma_and_no_thousands_separator(): void
    {
        $this->assertSame('12,50', CsvWriter::decimal(1250));
        $this->assertSame('0,05', CsvWriter::decimal(5));
        $this->assertSame('0,00', CsvWriter::decimal(0));
        $this->assertSame('1234567,89', CsvWriter::decimal(123456789));
        $this->assertSame('-0,05', CsvWriter::decimal(-5));
    }

    public function test_the_reader_reads_back_everything_the_writer_wrote(): void
    {
        $lines = [
            ['code', 'naam', 'leverancier'],
            ['LC-001', 'Hosting; met "quote"', "twee\nregels"],
            ['LC-002', '=SOM(A1)', '@a'],
        ];

        $path = $this->path();
        $handle = fopen($path, 'wb');
        $writer = new CsvWriter($handle);

        foreach ($lines as $fields) {
            $writer->write($fields);
        }

        fclose($handle);

        $read = array_values(iterator_to_array((new CsvReader($path))->lines()));

        $this->assertSame(['code', 'naam', 'leverancier'], $read[0]);
        $this->assertSame(['LC-001', 'Hosting; met "quote"', "twee\nregels"], $read[1]);
        $this->assertSame(['LC-002', "'=SOM(A1)", "'@a"], $read[2]);
    }

    public function test_it_writes_and_reads_back_five_megabytes_within_ten_seconds(): void
    {
        $path = $this->path();
        $start = microtime(true);

        $handle = fopen($path, 'wb');
        $writer = new CsvWriter($handle);
        $writer->write(['code', 'naam', 'leverancier', 'jaar', 'budget']);

        for ($row = 0; $row < 70000; $row++) {
            $writer->write([sprintf('LC-%06d', $row), 'Hosting; met "quote" en wat vulling', 'Leverancier '.$row, 2026, CsvWriter::decimal($row * 100 + 50)]);
        }

        fclose($handle);

        $read = iterator_count((new CsvReader($path))->lines());

        $this->assertGreaterThan(5242880, filesize($path));
        $this->assertSame(70001, $read);
        $this->assertLessThan(10.0, microtime(true) - $start);
    }
}
