<?php

namespace Tests\Unit\Exchange;

use App\Rules\AmountCents;
use App\Services\Exchange\CanvasImportValidator;
use Illuminate\Support\Str;
use Tests\Feature\Exchange\ExchangeTestCase;

class CanvasImportValidatorTest extends ExchangeTestCase
{
    private const REFUSAL = 'Bestand: kies een JSON-export van deze applicatie van maximaal 50 MB';

    /** @return array<string, mixed> */
    private function file(): array
    {
        $this->richCanvas();

        return $this->exported();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{location: string, message: string}>
     */
    private function errorsOf(array $data): array
    {
        return app(CanvasImportValidator::class)->validate((string) json_encode($data))['errors'];
    }

    /** @param array<string, mixed> $data */
    private function indexOf(array $data, string $name): int
    {
        foreach ($data['canvas']['elements'] as $index => $element) {
            if ($element['name'] === $name) {
                return $index;
            }
        }

        $this->fail('element not in the file: '.$name);
    }

    /** @param array<string, mixed> $data */
    private function unknownIndex(array $data): int
    {
        foreach ($data['canvas']['elements'] as $index => $element) {
            if ($element['is_unknown_allocation'] === true) {
                return $index;
            }
        }

        $this->fail('no unknown allocation element in the file');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changes
     */
    private function append(array &$data, int $source, array $changes): int
    {
        $data['canvas']['elements'][] = [...$data['canvas']['elements'][$source], 'id' => (string) Str::uuid(), ...$changes];

        return count($data['canvas']['elements']) - 1;
    }

    /** @param list<array{location: string, message: string}> $errors */
    private function assertErrorAt(string $location, array $errors): void
    {
        $messages = array_column(array_filter($errors, fn (array $error) => $error['location'] === $location), 'message');

        $this->assertNotSame([], $messages, 'no error reported at '.$location);
        $this->assertNotSame('', trim($messages[0]));
    }

    private function assertRefused(string $content): void
    {
        $result = app(CanvasImportValidator::class)->validate($content);

        $this->assertNull($result['data']);
        $this->assertSame([['location' => 'file', 'message' => self::REFUSAL]], $result['errors']);
        $this->assertSame(1, $result['count']);
    }

    public function test_a_valid_export_is_accepted_without_errors(): void
    {
        $result = app(CanvasImportValidator::class)->validate((string) json_encode($this->file()));

        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['count']);
        $this->assertNotNull($result['data']);
    }

    public function test_a_name_over_150_characters_is_reported_with_its_location(): void
    {
        $data = $this->file();
        $index = $this->indexOf($data, 'Bestellen');
        $data['canvas']['elements'][$index]['name'] = str_repeat('a', 151);

        $this->assertErrorAt("canvas.elements[$index].name", $this->errorsOf($data));
    }

    public function test_a_name_holding_the_forbidden_sign_is_reported_with_its_location(): void
    {
        $data = $this->file();
        $index = $this->indexOf($data, 'Offreren');
        $data['canvas']['elements'][$index]['name'] = 'Offreren > snel';

        $this->assertErrorAt("canvas.elements[$index].name", $this->errorsOf($data));
    }

    public function test_run_and_change_above_one_hundred_percent_are_reported_at_change(): void
    {
        $data = $this->file();
        $data['cost_records'][0]['yearly_figures'][1]['run_bp'] = 6000;
        $data['cost_records'][0]['yearly_figures'][1]['change_bp'] = 4001;

        $this->assertErrorAt('cost_records[0].yearly_figures[1].change_bp', $this->errorsOf($data));
    }

    public function test_an_amount_out_of_range_is_reported_with_its_location(): void
    {
        $data = $this->file();
        $data['cost_records'][0]['yearly_figures'][0]['budget_cents'] = AmountCents::MAX_CENTS + 1;

        $this->assertErrorAt('cost_records[0].yearly_figures[0].budget_cents', $this->errorsOf($data));
    }

    public function test_a_colour_outside_the_hexadecimal_form_is_reported_with_its_location(): void
    {
        $data = $this->file();
        $data['canvas']['layout'] = ['blocks' => [['fill' => 'red']]];

        $this->assertErrorAt('canvas.layout.blocks[0].fill', $this->errorsOf($data));
    }

    public function test_a_name_taken_by_an_active_sibling_is_reported_at_the_later_element(): void
    {
        $data = $this->file();
        $index = $this->append($data, $this->indexOf($data, 'Bestellen'), []);

        $this->assertErrorAt("canvas.elements[$index].name", $this->errorsOf($data));
    }

    public function test_a_cluster_under_a_cluster_is_reported_at_its_parent(): void
    {
        $data = $this->file();
        $cluster = $this->indexOf($data, 'Werving');
        $index = $this->append($data, $cluster, ['name' => 'Tweede werving', 'parent_id' => $data['canvas']['elements'][$cluster]['id']]);

        $this->assertErrorAt("canvas.elements[$index].parent_id", $this->errorsOf($data));
    }

    public function test_an_activity_under_a_parent_outside_the_file_is_reported_at_its_parent(): void
    {
        $data = $this->file();
        $index = $this->append($data, $this->indexOf($data, 'Offreren'), ['name' => 'Zwevend', 'parent_id' => (string) Str::uuid()]);

        $this->assertErrorAt("canvas.elements[$index].parent_id", $this->errorsOf($data));
    }

    public function test_a_second_unknown_allocation_element_is_reported(): void
    {
        $data = $this->file();
        $index = $this->append($data, $this->unknownIndex($data), ['name' => 'Tweede onbekende allocatie']);

        $this->assertErrorAt("canvas.elements[$index]", $this->errorsOf($data));
    }

    public function test_a_child_under_the_unknown_allocation_element_is_reported(): void
    {
        $data = $this->file();
        $unknown = $this->unknownIndex($data);
        $this->append($data, $this->indexOf($data, 'Schoonmaak'), ['name' => 'Onder onbekend', 'parent_id' => $data['canvas']['elements'][$unknown]['id']]);

        $this->assertErrorAt("canvas.elements[$unknown]", $this->errorsOf($data));
    }

    public function test_lines_that_do_not_sum_to_one_hundred_percent_are_reported_at_the_set(): void
    {
        $data = $this->file();
        $data['canvas']['allocation_sets'][0]['lines'][0]['basis_points'] = 3000;

        $this->assertErrorAt('canvas.allocation_sets[0]', $this->errorsOf($data));
    }

    public function test_only_the_first_thousand_errors_are_kept_and_the_count_holds_them_all(): void
    {
        $data = $this->file();
        $domain = $data['canvas']['elements'][$this->indexOf($data, 'Inkoop')];

        for ($number = 0; $number < 1200; $number++) {
            $data['canvas']['elements'][] = [...$domain, 'id' => (string) Str::uuid(), 'name' => ''];
        }

        $result = app(CanvasImportValidator::class)->validate((string) json_encode($data));

        $this->assertCount(1000, $result['errors']);
        $this->assertSame(1200, $result['count']);
    }

    public function test_an_unknown_format_version_is_refused(): void
    {
        $data = $this->file();
        $data['format_version'] = 2;

        $this->assertRefused((string) json_encode($data));
    }

    public function test_content_that_is_not_json_is_refused(): void
    {
        $this->assertRefused('{"format": "omc-studio-canvas"');
    }

    public function test_content_over_fifty_megabytes_is_refused(): void
    {
        $limit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        $this->assertRefused(str_repeat('a', CanvasImportValidator::MAX_BYTES + 1));

        ini_set('memory_limit', $limit);
    }

    public function test_a_root_that_is_not_an_object_is_refused(): void
    {
        $this->assertRefused('[1, 2, 3]');
    }
}
