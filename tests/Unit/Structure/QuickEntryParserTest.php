<?php

namespace Tests\Unit\Structure;

use App\Services\Structure\QuickEntryParser;
use Tests\TestCase;

class QuickEntryParserTest extends TestCase
{
    /**
     * @param  list<string>  $existing
     * @return array{lines: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}
     */
    private function parse(string $text, string $area = 'activities', array $existing = []): array
    {
        return (new QuickEntryParser)->parse($text, $area, $existing);
    }

    public function test_indent_levels_become_domain_cluster_and_activity(): void
    {
        $result = $this->parse("Inkoop\n  Werving\n    Offreren\n  Bestellen\nVerkoop\n\tOfferte");

        $this->assertSame([], $result['errors']);
        $this->assertSame(
            [['Inkoop', 'domain'], ['Werving', 'cluster'], ['Offreren', 'activity'], ['Bestellen', 'activity'], ['Verkoop', 'domain'], ['Offerte', 'activity']],
            array_map(fn ($line) => [$line['name'], $line['kind']], $result['lines']),
        );
        $this->assertSame('inkoop>werving>offreren', $result['lines'][2]['path']);
    }

    public function test_a_tab_and_two_spaces_are_the_same_indent(): void
    {
        $result = $this->parse("Inkoop\n\tWerving\n  \tOffreren");

        $this->assertSame([], $result['errors']);
        $this->assertSame('cluster', $result['lines'][1]['kind']);
        $this->assertSame('activity', $result['lines'][2]['kind']);
    }

    public function test_empty_lines_are_ignored_but_keep_their_line_numbers(): void
    {
        $result = $this->parse("Inkoop\n\n   \n  Offreren\r\n");

        $this->assertSame([], $result['errors']);
        $this->assertSame([1, 4], array_column($result['lines'], 'line'));
    }

    public function test_lines_are_marked_new_or_existing(): void
    {
        $result = $this->parse("Inkoop\n  Offreren\n  Bestellen", 'activities', ['inkoop', 'inkoop>offreren']);

        $this->assertSame(['exists', 'exists', 'new'], array_column($result['lines'], 'status'));
    }

    public function test_a_first_line_that_is_indented_is_an_error(): void
    {
        $this->assertSame(['Regel 1: inspringing past niet bij de regel erboven'], array_column($this->parse('  Offreren')['errors'], 'message'));
    }

    public function test_two_indents_under_no_indent_and_three_indents_are_errors(): void
    {
        $result = $this->parse("Inkoop\nVerkoop\n    Offreren\n  Werving\n      Diep");

        $this->assertSame([
            ['line' => 3, 'message' => 'Regel 3: inspringing past niet bij de regel erboven'],
            ['line' => 5, 'message' => 'Regel 5: inspringing past niet bij de regel erboven'],
        ], $result['errors']);
    }

    public function test_a_list_area_refuses_two_indents(): void
    {
        $result = $this->parse("Leveranciers\n  Drukkerij\n    Papier", 'list');

        $this->assertSame([['line' => 3, 'message' => 'Regel 3: in deze area bestaan geen clusters']], $result['errors']);
        $this->assertSame('activity', $result['lines'][1]['kind']);
    }

    public function test_duplicates_under_the_same_parent_are_reported_on_the_second_line(): void
    {
        $result = $this->parse("Inkoop\n  Offreren\n  offreren\nVerkoop\n  Offreren");

        $this->assertSame([['line' => 3, 'message' => 'Regel 3: deze naam staat al onder dezelfde regel erboven']], $result['errors']);
    }

    public function test_a_sign_and_a_long_name_are_reported_per_line(): void
    {
        $result = $this->parse("A > B\n".str_repeat('x', 151)."\n".str_repeat('y', 150));

        $this->assertSame([
            ['line' => 1, 'message' => 'Regel 1: het teken > is niet toegestaan'],
            ['line' => 2, 'message' => 'Regel 2: de naam is langer dan 150 tekens'],
        ], $result['errors']);
    }

    public function test_the_unknown_allocation_domain_is_system_managed_with_its_lines(): void
    {
        $result = $this->parse("onbekende allocatie\n  Iets");

        $this->assertSame([
            ['line' => 1, 'message' => 'Regel 1: Onbekende allocatie wordt door het systeem beheerd'],
            ['line' => 2, 'message' => 'Regel 2: Onbekende allocatie wordt door het systeem beheerd'],
        ], $result['errors']);
    }
}
