<?php

namespace Tests\Unit\Models;

use App\Models\Area;
use App\Models\Modality;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_area_columns_are_exactly_those_of_the_data_model(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'key', 'kind', 'position', 'carries_costs'],
            Schema::getColumnListing('areas'),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'key', 'letter', 'colour', 'position'],
            Schema::getColumnListing('modalities'),
        );
    }

    public function test_the_keys_are_unique_and_the_position_is_indexed(): void
    {
        foreach (['areas', 'modalities'] as $table) {
            $indexes = collect(Schema::getIndexes($table));

            $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['key'] && $i['unique']), "$table unique(key)");
            $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['position'] && ! $i['unique']), "$table index(position)");
        }

        Area::create(['key' => 'direction', 'kind' => 'text', 'position' => 1, 'carries_costs' => false]);
        $this->expectException(UniqueConstraintViolationException::class);
        Area::create(['key' => 'direction', 'kind' => 'text', 'position' => 2, 'carries_costs' => false]);
    }

    public function test_an_area_and_a_modality_round_trip(): void
    {
        $area = Area::create(['key' => 'steering', 'kind' => 'activities', 'position' => 2, 'carries_costs' => true]);
        $modality = Modality::create(['key' => 'common', 'letter' => 'C', 'colour' => '#1F7A4D', 'position' => 1]);

        $this->assertTrue($area->fresh()->carries_costs);
        $this->assertSame('#1F7A4D', $modality->fresh()->colour);
    }

    public function test_every_area_has_its_labels_in_the_messages_file(): void
    {
        $messages = json_decode((string) file_get_contents(lang_path('nl.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            ['direction', 'steering', 'governing', 'actors', 'channels', 'value_propositions', 'primary_activities', 'supporting_activities'],
            Area::KEYS,
        );

        foreach (Area::KEYS as $key) {
            $this->assertNotEmpty($messages["areas.$key.name"] ?? null, "areas.$key.name");
            if ($key !== 'direction') {
                $this->assertNotEmpty($messages["areas.$key.level_1"] ?? null, "areas.$key.level_1");
                $this->assertNotEmpty($messages["areas.$key.level_2"] ?? null, "areas.$key.level_2");
            }
        }
    }

    public function test_no_route_writes_the_reference_data(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (preg_match('#(^|/)(areas|modalities)(/|$)#', $route->uri())) {
                $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri());
            }
        }

        $this->assertTrue(true);
    }
}
