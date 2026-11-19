<?php

namespace Tests\Unit\Exchange;

use App\Rules\CsvUploadRule;
use App\Services\Exchange\Csv\CsvReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CsvReaderTest extends TestCase
{
    private const COLUMNS = ['code', 'naam', 'soort', 'leverancier', 'eigenaar', 'kostenplaats', 'applicatie', 'jaar', 'budget', 'prognose', 'run_pct', 'change_pct', 'fte'];

    private const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

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

    private function file(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);
        $this->paths[] = $path;

        return $path;
    }

    /** @return array<int, list<string>> */
    private function read(string $content): array
    {
        return iterator_to_array((new CsvReader($this->file($content)))->lines());
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function errors(UploadedFile|string $file, array $columns = self::COLUMNS): array
    {
        return Validator::make(['file' => $file], ['file' => [new CsvUploadRule($columns)]])->errors()->get('file');
    }

    private function upload(string $content, string $name = 'kosten.csv'): UploadedFile
    {
        return new UploadedFile($this->file($content), $name, 'text/csv', null, true);
    }

    public function test_it_numbers_the_header_as_line_one_and_data_from_line_two(): void
    {
        $this->assertSame([
            1 => ['code', 'naam'],
            2 => ['LC-001', 'Hosting'],
            3 => ['LC-002', 'Licenties'],
        ], $this->read("code;naam\nLC-001;Hosting\nLC-002;Licenties\n"));
    }

    public function test_it_accepts_a_byte_order_mark_and_carriage_returns(): void
    {
        $this->assertSame([
            1 => ['code', 'naam'],
            2 => ['LC-001', 'Hosting'],
        ], $this->read("\xEF\xBB\xBFcode;naam\r\nLC-001;Hosting\r\n"));
    }

    public function test_it_reads_quoted_fields_and_counts_their_line_breaks(): void
    {
        $lines = $this->read("code;naam\nLC-001;\"Hosting; met \"\"quote\"\"\nen een tweede regel\"\nLC-002;Licenties\n");

        $this->assertSame(['LC-001', "Hosting; met \"quote\"\nen een tweede regel"], $lines[2]);
        $this->assertSame(['LC-002', 'Licenties'], $lines[4]);
        $this->assertCount(3, $lines);
    }

    public function test_it_skips_blank_lines_but_keeps_counting_them(): void
    {
        $this->assertSame([
            1 => ['code', 'naam'],
            3 => ['LC-002', 'Licenties'],
        ], $this->read("code;naam\n\nLC-002;Licenties\n"));
    }

    public function test_it_yields_nothing_for_a_missing_file(): void
    {
        $this->assertSame([], iterator_to_array((new CsvReader(sys_get_temp_dir().'/absent.csv'))->lines()));
    }

    public function test_the_rule_accepts_the_expected_header_with_or_without_a_byte_order_mark(): void
    {
        $this->assertSame([], $this->errors($this->upload(self::HEADER."\nLC-001;Hosting;overig;;;;;2026;12,50;;;;\n")));
        $this->assertSame([], $this->errors($this->upload("\xEF\xBB\xBF".self::HEADER."\r\n")));
    }

    public function test_the_rule_takes_the_expected_header_as_a_parameter(): void
    {
        $this->assertSame([], $this->errors($this->upload("area;domein\n"), ['area', 'domein']));
        $this->assertCount(1, $this->errors($this->upload(self::HEADER."\n"), ['area', 'domein']));
    }

    public function test_the_rule_refuses_another_header_with_the_column_message(): void
    {
        $this->assertSame(
            ['Bestand: kies een CSV-bestand van maximaal 5 MB met de kolommen '.self::HEADER],
            $this->errors($this->upload("code;naam\nLC-001;Hosting\n")),
        );
    }

    public function test_the_rule_refuses_a_file_over_five_megabytes_with_a_valid_header(): void
    {
        $content = self::HEADER."\n".str_repeat("LC-001;Hosting;overig;;;;;2026;12,50;;;;\n", 140000);

        $this->assertGreaterThan(5242880, strlen($content));
        $this->assertCount(1, $this->errors($this->upload($content)));
    }

    public function test_the_rule_refuses_content_that_is_not_text_despite_a_valid_header(): void
    {
        $this->assertCount(1, $this->errors($this->upload(self::HEADER."\nLC-001;\x00\x01\x02;overig\n", 'kosten.csv')));
        $this->assertCount(1, $this->errors($this->upload(self::HEADER."\nLC-001;\xFF\xFE".'Hosting'."\n")));
    }

    public function test_the_rule_refuses_an_empty_file_and_anything_that_is_not_an_upload(): void
    {
        $this->assertCount(1, $this->errors($this->upload('')));
        $this->assertCount(1, $this->errors('kosten.csv'));
    }
}
