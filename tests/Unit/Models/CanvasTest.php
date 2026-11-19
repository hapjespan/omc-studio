<?php

namespace Tests\Unit\Models;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CanvasTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_name_is_allowed_in_two_organisations(): void
    {
        Canvas::factory()->create(['name' => 'Operating model']);
        Canvas::factory()->create(['name' => 'Operating model']);

        $this->assertSame(2, Canvas::count());
    }

    public function test_the_same_name_is_refused_twice_in_one_organisation_ignoring_case(): void
    {
        $organisation = Organisation::factory()->create();
        Canvas::factory()->for($organisation)->create(['name' => 'Operating model']);

        $this->expectException(UniqueConstraintViolationException::class);

        Canvas::factory()->for($organisation)->create(['name' => ' OPERATING model']);
    }

    public function test_templates_have_no_organisation_and_unique_names_among_templates(): void
    {
        $template = Canvas::factory()->template()->create(['name' => 'Sjabloon zorg']);

        $this->assertNull($template->organisation_id);
        $this->assertSame('template', $template->scope_key);
        $this->assertSame(CanvasKind::Template, $template->fresh()->kind);

        Canvas::factory()->create(['name' => 'Sjabloon zorg']);

        $this->expectException(UniqueConstraintViolationException::class);

        Canvas::factory()->template()->create(['name' => 'sjabloon ZORG']);
    }

    public function test_the_scope_key_follows_the_organisation(): void
    {
        $canvas = Canvas::factory()->create();

        $this->assertSame('organisation:'.$canvas->organisation_id, $canvas->scope_key);
        $this->assertFalse($canvas->is_blank_template);
    }

    public function test_an_organisation_with_canvases_cannot_be_deleted(): void
    {
        $canvas = Canvas::factory()->create();

        $this->expectException(QueryException::class);

        $canvas->organisation->delete();
    }

    public function test_the_indexes_are_plain_and_complete(): void
    {
        $indexes = collect(Schema::getIndexes('canvases'));

        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['scope_key', 'name_normalised'] && $i['unique']));
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['organisation_id', 'archived_at']));
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['kind']));
        $this->assertSame([], $indexes->filter(fn ($i) => ! empty($i['where'] ?? null))->all());
    }
}
