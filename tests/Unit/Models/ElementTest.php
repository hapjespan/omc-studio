<?php

namespace Tests\Unit\Models;

use App\Enums\ElementKind;
use App\Models\Canvas;
use App\Models\Element;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ElementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_identifier_is_a_uuid_and_the_name_is_normalised(): void
    {
        $element = Element::factory()->create(['name' => '  Intake Zorg ']);

        $this->assertTrue(Str::isUuid($element->id));
        $this->assertSame('intake zorg', $element->fresh()->name_normalised);
        $this->assertSame(ElementKind::Domain, $element->fresh()->kind);
        $this->assertFalse($element->fresh()->is_unknown_allocation);
        $this->assertNull($element->fresh()->archived_at);
    }

    public function test_every_index_of_the_data_model_exists_and_none_is_partial(): void
    {
        $indexes = collect(Schema::getIndexes('elements'));

        foreach ([
            ['canvas_id', 'parent_id', 'position'],
            ['canvas_id', 'area_id', 'parent_id', 'name_normalised'],
            ['canvas_id', 'is_unknown_allocation'],
            ['canvas_id', 'archived_at'],
        ] as $columns) {
            $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === $columns && ! $i['unique']), implode(',', $columns));
        }

        $this->assertSame([], $indexes->filter(fn ($i) => $i['unique'] && ! $i['primary'])->all());
    }

    public function test_creating_changing_and_deleting_an_element_touches_the_canvas(): void
    {
        $canvas = Canvas::factory()->create();
        $canvas->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $element = Element::factory()->for($canvas)->create();
        $this->assertTrue($canvas->fresh()->updated_at->isToday());

        $canvas->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $element->update(['name' => 'Anders']);
        $this->assertTrue($canvas->fresh()->updated_at->isToday());

        $canvas->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $element->delete();
        $this->assertTrue($canvas->fresh()->updated_at->isToday());
    }

    public function test_an_element_with_children_cannot_be_deleted(): void
    {
        $domain = Element::factory()->create();
        Element::factory()->for($domain->canvas)->create(['parent_id' => $domain->id, 'kind' => ElementKind::Activity]);

        $this->expectException(QueryException::class);

        $domain->delete();
    }

    public function test_a_canvas_removes_its_whole_tree(): void
    {
        $domain = Element::factory()->create();
        Element::factory()->for($domain->canvas)->create(['parent_id' => $domain->id, 'kind' => ElementKind::Activity]);

        $domain->canvas->delete();

        $this->assertSame(0, DB::table('elements')->count());
    }

    public function test_the_area_key_restricts_deletion(): void
    {
        $element = Element::factory()->create();

        $this->expectException(QueryException::class);

        $element->area->delete();
    }
}
