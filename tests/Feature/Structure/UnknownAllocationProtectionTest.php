<?php

namespace Tests\Feature\Structure;

use App\Enums\ElementKind;
use App\Models\Element;
use App\Models\User;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Structure\ElementArchive;
use App\Services\Structure\ElementDeletion;
use Closure;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;

class UnknownAllocationProtectionTest extends StructureTestCase
{
    private const MESSAGE = 'Onbekende allocatie wordt door het systeem beheerd';

    /** @return Generator<string, array{Closure(self, Element): mixed}> */
    public static function writes(): Generator
    {
        yield 'move it' => [fn (self $t, Element $unknown) => $t->tree()->move($unknown, $t->area('primary_activities'))];
        yield 'reorder it' => [fn (self $t, Element $unknown) => $t->tree()->reorder($unknown, 'up')];
        yield 'archive it' => [fn (self $t, Element $unknown) => app(ElementArchive::class)->archive($unknown)];
        yield 'restore it' => [fn (self $t, Element $unknown) => app(ElementArchive::class)->restore($unknown)];
        yield 'delete it' => [fn (self $t, Element $unknown) => app(ElementDeletion::class)->delete($unknown, User::factory()->administrator()->create())];
        yield 'add a child' => [fn (self $t, Element $unknown) => $t->tree()->create($t->canvas, $unknown->area, ElementKind::Activity, $unknown->id, ['name' => 'Kind'])];
        yield 'move an element into it' => [function (self $t, Element $unknown) {
            $other = $t->domain('supporting_activities', 'Anders');
            $activity = $t->activity($other);
            $t->tree()->move($activity, $unknown);
        }];
    }

    #[DataProvider('writes')]
    public function test_the_write_is_refused_and_nothing_changes(Closure $write): void
    {
        $unknown = UnknownAllocationDomain::ensure($this->canvas);
        $before = $unknown->fresh()->getAttributes();

        $message = $this->refusal(fn () => $write($this, $unknown));

        $this->assertSame(self::MESSAGE, $message);
        $this->assertSame($before, $unknown->fresh()->getAttributes());
        $this->assertSame(0, Element::where('parent_id', $unknown->id)->count());
    }
}
