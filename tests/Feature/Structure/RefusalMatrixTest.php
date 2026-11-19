<?php

namespace Tests\Feature\Structure;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\User;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Structure\ElementArchive;
use Closure;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;

class RefusalMatrixTest extends StructureTestCase
{
    private const NAME_TAKEN = 'Naam: er staat al een element met deze naam op deze plek';

    private const UNKNOWN = 'Onbekende allocatie wordt door het systeem beheerd';

    /** @return Generator<string, array{string, Closure(self): array{string, string, array<string, mixed>}}> */
    public static function refusals(): Generator
    {
        $base = fn (self $t) => "/canvases/{$t->canvas->id}/elements";
        $create = fn (self $t, array $data) => ['post', $base($t), $data];

        yield 'sc1 activity without a parent' => ['Een activiteit hoort onder een domein of een cluster', fn (self $t) => $create($t, ['kind' => 'activity', 'area' => 'primary_activities', 'name' => 'A'])];
        yield 'sc1 activity under an activity' => ['Een activiteit hoort onder een domein of een cluster', function (self $t) use ($create) {
            $activity = $t->activity($t->domain());

            return $create($t, ['kind' => 'activity', 'area' => 'primary_activities', 'parent_id' => $activity->id, 'name' => 'A']);
        }];
        yield 'sc1 activity under a domain of another canvas' => ['Een activiteit hoort onder een domein of een cluster', function (self $t) use ($create) {
            $foreign = $t->domain('primary_activities', 'Vreemd', Canvas::factory()->create());

            return $create($t, ['kind' => 'activity', 'area' => 'primary_activities', 'parent_id' => $foreign->id, 'name' => 'A']);
        }];
        yield 'sc1 activity under a missing parent' => ['Een activiteit hoort onder een domein of een cluster', fn (self $t) => $create($t, ['kind' => 'activity', 'area' => 'primary_activities', 'parent_id' => '00000000-0000-4000-8000-000000000000', 'name' => 'A'])];
        yield 'sc2 cluster under a cluster' => ['Een cluster kan niet in een ander cluster', function (self $t) use ($create) {
            $cluster = $t->cluster($t->domain());

            return $create($t, ['kind' => 'cluster', 'area' => 'primary_activities', 'parent_id' => $cluster->id, 'name' => 'C']);
        }];
        foreach (['actors', 'channels', 'value_propositions'] as $key) {
            yield "sc3 cluster in $key" => ['In deze area bestaan geen clusters', function (self $t) use ($create, $key) {
                $group = $t->domain($key, 'Groep');

                return $create($t, ['kind' => 'cluster', 'area' => $key, 'parent_id' => $group->id, 'name' => 'C']);
            }];
        }
        foreach (['domain', 'cluster', 'activity'] as $kind) {
            yield "sc4 $kind in direction" => ['In Koers staan geen elementen', fn (self $t) => $create($t, ['kind' => $kind, 'area' => 'direction', 'name' => 'K'])];
        }
        yield 'sc6 duplicate name ignoring case and padding' => [self::NAME_TAKEN, function (self $t) use ($create) {
            $domain = $t->domain('primary_activities', 'Personeel');
            $t->activity($domain, 'Werving');

            return $create($t, ['kind' => 'activity', 'area' => 'primary_activities', 'parent_id' => $domain->id, 'name' => 'werving ']);
        }];
        yield 'sc7 name with the greater than sign' => ['Naam: het teken > is niet toegestaan', fn (self $t) => $create($t, ['kind' => 'domain', 'area' => 'primary_activities', 'name' => 'A > B'])];
        yield 'sc7 rename with the greater than sign' => ['Naam: het teken > is niet toegestaan', fn (self $t) => ['patch', $base($t).'/'.$t->domain()->id, ['name' => 'A > B']]];
        yield 'sc8 activity into an area of the other kind' => ['Verplaatsen kan alleen naar een area van dezelfde soort', function (self $t) use ($base) {
            $activity = $t->activity($t->domain());
            $group = $t->domain('actors', 'Groep');

            return ['patch', $base($t).'/'.$activity->id, ['parent_id' => $group->id]];
        }];
        yield 'sc8 list domain into an activity area' => ['Verplaatsen kan alleen naar een area van dezelfde soort', fn (self $t) => ['patch', $base($t).'/'.$t->domain('actors', 'Groep')->id, ['area' => 'steering']]];
        yield 'sc13 lifting activities onto a taken name' => ['Terugzetten kan niet: werving staat al onder het domein', function (self $t) use ($base) {
            $domain = $t->domain();
            $cluster = $t->cluster($domain, 'Groep');
            $t->activity($domain, 'Werving');
            $t->activity($cluster, 'werving');

            return ['delete', $base($t).'/'.$cluster->id, ['cluster_children' => 'lift']];
        }];
        yield 'sc15 restore under an archived parent' => ['Terugzetten kan niet: het bovenliggende element is gearchiveerd', function (self $t) use ($base) {
            $domain = $t->domain();
            $activity = $t->activity($domain);
            app(ElementArchive::class)->archive($activity);
            app(ElementArchive::class)->archive($domain);

            return ['post', $base($t).'/'.$activity->id.'/restore', []];
        }];
        yield 'sc15 restore onto a taken name' => [self::NAME_TAKEN, function (self $t) use ($base) {
            $domain = $t->domain();
            $activity = $t->activity($domain, 'Werving');
            app(ElementArchive::class)->archive($activity);
            $t->activity($domain, 'werving');

            return ['post', $base($t).'/'.$activity->id.'/restore', []];
        }];
        yield 'sc16 rename the unknown domain' => [self::UNKNOWN, fn (self $t) => ['patch', $base($t).'/'.UnknownAllocationDomain::ensure($t->canvas)->id, ['name' => 'Anders']]];
        yield 'sc16 edit the unknown domain' => [self::UNKNOWN, fn (self $t) => ['patch', $base($t).'/'.UnknownAllocationDomain::ensure($t->canvas)->id, ['description' => 'Tekst']]];
        yield 'sc16 move the unknown domain' => [self::UNKNOWN, fn (self $t) => ['patch', $base($t).'/'.UnknownAllocationDomain::ensure($t->canvas)->id, ['area' => 'primary_activities']]];
        yield 'sc16 reorder the unknown domain' => [self::UNKNOWN, fn (self $t) => ['post', $base($t).'/'.UnknownAllocationDomain::ensure($t->canvas)->id.'/reorder', ['direction' => 'up']]];
        yield 'sc16 archive the unknown domain' => [self::UNKNOWN, fn (self $t) => ['post', $base($t).'/'.UnknownAllocationDomain::ensure($t->canvas)->id.'/archive', []]];
        yield 'sc16 delete the unknown domain' => [self::UNKNOWN, fn (self $t) => ['delete', $base($t).'/'.UnknownAllocationDomain::ensure($t->canvas)->id, []]];
        yield 'sc16 add a child to the unknown domain' => [self::UNKNOWN, fn (self $t) => $create($t, ['kind' => 'activity', 'area' => 'supporting_activities', 'parent_id' => UnknownAllocationDomain::ensure($t->canvas)->id, 'name' => 'Kind'])];
        yield 'sc16 move an element into the unknown domain' => [self::UNKNOWN, function (self $t) use ($base) {
            $activity = $t->activity($t->domain('supporting_activities', 'Gewoon'));

            return ['patch', $base($t).'/'.$activity->id, ['parent_id' => UnknownAllocationDomain::ensure($t->canvas)->id]];
        }];
    }

    /** @param Closure(self): array{string, string, array<string, mixed>} $request */
    #[DataProvider('refusals')]
    public function test_the_server_refuses_with_the_message_and_changes_nothing(string $message, Closure $request): void
    {
        [$method, $uri, $data] = $request($this);
        $before = $this->snapshot();

        $this->actingAs(User::factory()->administrator()->create())
            ->{$method}($uri, $data)
            ->assertSessionHasErrors(['rule' => $message]);

        $this->assertSame($before, $this->snapshot());
    }

    /** @return list<array<string, mixed>> */
    private function snapshot(): array
    {
        return Element::query()->orderBy('id')->get()->map(fn (Element $element) => $element->getAttributes())->all();
    }
}
