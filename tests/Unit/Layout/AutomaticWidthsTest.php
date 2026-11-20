<?php

namespace Tests\Unit\Layout;

use App\Services\Layout\AutomaticWidths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AutomaticWidthsTest extends TestCase
{
    /** @return array<string, array{0: array<string, mixed>}> */
    public static function cases(): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/automatic-widths.json'), true, flags: JSON_THROW_ON_ERROR);

        return array_column(array_map(fn (array $case) => [$case['name'], [$case]], $fixture['cases']), 1, 0);
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('cases')]
    public function test_the_widths_follow_the_shared_fixture(array $case): void
    {
        $widths = AutomaticWidths::compute($case['elements'], $case['areaWidths'] ?? [], $case['elementWidths'] ?? []);

        $this->assertSame($case['expected'], $widths);
    }
}
