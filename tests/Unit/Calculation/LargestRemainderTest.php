<?php

namespace Tests\Unit\Calculation;

use App\Calculation\LargestRemainder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LargestRemainderTest extends TestCase
{
    /** @return array<string, array{int, list<int>, list<int>}> */
    public static function cases(): array
    {
        $cases = [];
        foreach (json_decode((string) file_get_contents(__DIR__.'/../../fixtures/largest-remainder.json'), true, 512, JSON_THROW_ON_ERROR) as $case) {
            $cases[$case['name']] = [$case['total'], $case['weights'], $case['expected']];
        }

        return $cases;
    }

    /**
     * @param  list<int>  $weights
     * @param  list<int>  $expected
     */
    #[DataProvider('cases')]
    public function test_split_matches_the_shared_fixture(int $total, array $weights, array $expected): void
    {
        $this->assertSame($expected, LargestRemainder::split($total, $weights));
        $this->assertSame($weights === [] ? 0 : $total, array_sum($expected));
    }
}
