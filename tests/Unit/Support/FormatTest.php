<?php

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    /** @return iterable<string, array{string, list<string|int>, string}> */
    public static function cases(): iterable
    {
        $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/fixtures/formats.json'), true, 512, JSON_THROW_ON_ERROR);

        foreach ($fixture as $function => $cases) {
            foreach ($cases as $index => $case) {
                yield "$function #$index" => [$function, $case['in'], $case['out']];
            }
        }
    }

    /** @param list<string|int> $arguments */
    #[DataProvider('cases')]
    public function test_the_shared_fixture_holds(string $function, array $arguments, string $expected): void
    {
        $this->assertSame($expected, Format::$function(...$arguments));
    }
}
