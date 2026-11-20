<?php

namespace Tests\Unit\Support;

use App\Support\Colour;
use PHPUnit\Framework\TestCase;

class ColourTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../fixtures/contrast.json'), true);
    }

    public function test_the_text_colour_follows_the_fixture(): void
    {
        foreach ($this->fixture()['cases'] as $case) {
            $this->assertSame($case['text'], Colour::autoText($case['background']), $case['background']);
        }
    }

    public function test_the_effective_fill_composites_opacity_over_the_fill_behind(): void
    {
        foreach ($this->fixture()['effectiveCases'] as $case) {
            $effective = Colour::effectiveFill($case['fill'], $case['opacity'], $case['behind']);

            $this->assertSame($case['effective'], $effective);
            $this->assertSame($case['text'], Colour::autoText($effective));
        }
    }

    public function test_every_default_colour_pair_meets_the_minimum_ratio(): void
    {
        $pairs = Colour::defaultPairs();

        $this->assertGreaterThan(8, count($pairs));

        foreach ($pairs as $pair) {
            $this->assertGreaterThanOrEqual($this->fixture()['minimumRatio'], Colour::contrast($pair['background'], $pair['text']), $pair['background']);
        }
    }

    public function test_the_ratio_cases_of_the_fixture_read_and_pass_as_in_typescript(): void
    {
        $cases = $this->fixture()['ratioCases'];

        $this->assertCount(9, $cases);

        foreach ($cases as $case) {
            $ratio = Colour::contrast($case['text'], $case['background']);

            $this->assertSame($case['label'], Colour::ratioLabel($ratio), $case['text'].' on '.$case['background']);
            $this->assertSame($case['passes'], $ratio >= $this->fixture()['minimumRatio']);
        }
    }

    public function test_the_direction_labels_are_readable_on_the_band(): void
    {
        $defaults = Colour::defaults();

        $this->assertGreaterThanOrEqual(4.5, Colour::contrast($defaults['directionLabels'], $defaults['areas']['direction']['domain']));
    }
}
