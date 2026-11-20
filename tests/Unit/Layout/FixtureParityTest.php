<?php

namespace Tests\Unit\Layout;

use App\Services\Layout\AutomaticWidths;
use App\Support\Colour;
use PHPUnit\Framework\TestCase;

class FixtureParityTest extends TestCase
{
    private const WIDTH_CASES = 5;

    private const CONTRAST_CASES = 11;

    private const EFFECTIVE_CASES = 5;

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../fixtures/'.$name), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_every_width_case_is_evaluated_and_the_case_count_matches_the_twin(): void
    {
        $cases = $this->fixture('automatic-widths.json')['cases'];

        $this->assertCount(self::WIDTH_CASES, $cases);

        foreach ($cases as $case) {
            $this->assertSame($case['expected'], AutomaticWidths::compute($case['elements'], $case['areaWidths'] ?? [], $case['elementWidths'] ?? []), $case['name']);
        }
    }

    public function test_every_contrast_case_is_evaluated_and_the_case_count_matches_the_twin(): void
    {
        $fixture = $this->fixture('contrast.json');

        $this->assertCount(self::CONTRAST_CASES, $fixture['cases']);
        $this->assertCount(self::EFFECTIVE_CASES, $fixture['effectiveCases']);

        foreach ($fixture['cases'] as $case) {
            $this->assertSame($case['text'], Colour::autoText($case['background']), $case['background']);
        }

        foreach ($fixture['effectiveCases'] as $case) {
            $this->assertSame($case['effective'], Colour::effectiveFill($case['fill'], $case['opacity'], $case['behind']));
        }
    }
}
