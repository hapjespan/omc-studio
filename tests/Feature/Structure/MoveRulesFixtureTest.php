<?php

namespace Tests\Feature\Structure;

use App\Enums\ElementKind;
use App\Models\Element;
use PHPUnit\Framework\Attributes\DataProvider;

class MoveRulesFixtureTest extends StructureTestCase
{
    /** @var array<string, mixed> */
    private static array $fixture;

    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        return self::$fixture ??= json_decode((string) file_get_contents(__DIR__.'/../../fixtures/move-rules.json'), true);
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function cases(): array
    {
        $cases = [];

        foreach (self::fixture()['cases'] as $case) {
            $cases[$case['dragged'].' onto '.$case['target']] = [$case['dragged'], $case['target'], $case['server']];
        }

        return $cases;
    }

    /** @return array<string, Element> */
    private function build(): array
    {
        $built = [];

        foreach (self::fixture()['elements'] as $row) {
            $parent = $row['parent'] === null ? null : $built[$row['parent']];
            $built[$row['id']] = $this->tree()->create($this->canvas, $this->area($row['area']), ElementKind::from($row['kind']), $parent?->id, ['name' => $row['name']]);

            if ($row['unknownAllocation'] ?? false) {
                $built[$row['id']]->forceFill(['is_unknown_allocation' => true])->save();
            }
        }

        return $built;
    }

    #[DataProvider('cases')]
    public function test_the_server_agrees_with_the_shared_move_rules(string $dragged, string $target, ?string $rule): void
    {
        $built = $this->build();

        $refusal = $this->refusal(fn () => $this->tree()->move($built[$dragged], $built[$target]));

        $this->assertSame($rule === null ? null : __('rule.'.$rule), $refusal);
    }
}
