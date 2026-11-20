<?php

namespace Tests\Feature\Layout;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use Dotenv\Dotenv;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LayoutSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const PG_SCHEMA = 'layout_schema_test';

    public function test_the_layout_schema_holds_on_sqlite(): void
    {
        $this->assertTheLayoutSchemaHolds();
    }

    public function test_the_layout_schema_holds_on_postgresql(): void
    {
        config(['database.connections.portability' => array_merge(
            config('database.connections.pgsql'),
            ['driver' => 'pgsql', 'search_path' => self::PG_SCHEMA, 'database' => $this->postgresDatabase()],
        )]);

        try {
            $pg = DB::connection('portability');
            $pg->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('PostgreSQL is not reachable: '.$e->getMessage());
        }

        $pg->statement('drop schema if exists '.self::PG_SCHEMA.' cascade');
        $pg->statement('create schema '.self::PG_SCHEMA);

        try {
            $this->assertSame(0, Artisan::call('migrate:fresh', ['--database' => 'portability', '--force' => true]));

            config(['database.default' => 'portability']);

            $this->assertTheLayoutSchemaHolds();
        } finally {
            config(['database.default' => 'sqlite']);
            $pg->statement('drop schema if exists '.self::PG_SCHEMA.' cascade');
        }
    }

    public function test_a_canvas_without_rows_has_the_automatic_layout(): void
    {
        $canvas = Canvas::factory()->create()->fresh();

        $this->assertSame(0, $canvas->layouts()->count());
        $this->assertSame(0, $canvas->structure_version);
        $this->assertSame(0, $canvas->layout_version);
        $this->assertNull($canvas->layout_saved_at);
    }

    public function test_the_versions_are_not_fillable_and_the_save_moment_is_a_date(): void
    {
        $canvas = Canvas::factory()->create();

        $this->assertFalse($canvas->isFillable('structure_version'));
        $this->assertFalse($canvas->isFillable('layout_version'));

        $canvas->forceFill(['layout_saved_at' => '2026-11-19 10:30:00'])->saveQuietly();

        $this->assertInstanceOf(Carbon::class, $canvas->fresh()->layout_saved_at);
    }

    public function test_a_layout_disappears_with_its_element_and_with_its_canvas(): void
    {
        $canvas = Canvas::factory()->create();
        $element = Element::factory()->for($canvas)->create();

        Layout::create($this->row($canvas, ['area_id' => $element->area_id]));
        Layout::create($this->row($canvas, ['element_id' => $element->id]));

        $element->delete();
        $this->assertSame(1, Layout::count());

        $canvas->delete();
        $this->assertSame(0, Layout::count());
    }

    private function assertTheLayoutSchemaHolds(): void
    {
        $indexes = collect(Schema::getIndexes('layouts'));

        $this->assertTrue($indexes->contains(fn ($index) => $index['columns'] === ['element_id'] && $index['unique']), 'unique(element_id)');
        $this->assertTrue($indexes->contains(fn ($index) => $index['columns'] === ['canvas_id', 'area_id'] && $index['unique']), 'unique(canvas_id, area_id)');
        $this->assertTrue($indexes->contains(fn ($index) => $index['columns'] === ['canvas_id'] && ! $index['unique']), 'index(canvas_id)');

        $canvas = Canvas::factory()->create();
        $element = Element::factory()->for($canvas)->create();
        $other = Element::factory()->for($canvas)->create();

        $area = Layout::create($this->row($canvas, ['area_id' => $element->area_id]));
        $own = Layout::create($this->row($canvas, ['element_id' => $element->id]));

        $this->assertSame(2, Layout::where('canvas_id', $canvas->id)->count());
        $this->assertSame(1, $area->fresh()->format_version);
        $this->assertSame(['fill' => '#1B4D7A'], $own->fresh()->style);
        $this->assertNull($own->fresh()->area_id);
        $this->assertNull($area->fresh()->element_id);

        $this->assertRefused(QueryException::class, $this->row($canvas, []));
        $this->assertRefused(QueryException::class, $this->row($canvas, ['area_id' => $element->area_id, 'element_id' => $other->id]));
        $this->assertRefused(UniqueConstraintViolationException::class, $this->row($canvas, ['area_id' => $element->area_id]));
        $this->assertRefused(UniqueConstraintViolationException::class, $this->row($canvas, ['element_id' => $element->id]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function row(Canvas $canvas, array $attributes): array
    {
        return array_merge([
            'canvas_id' => $canvas->id,
            'x' => 40,
            'y' => 24,
            'width' => 200,
            'height' => 80,
            'z' => 0,
            'style' => ['fill' => '#1B4D7A'],
        ], $attributes);
    }

    /**
     * @param  class-string<\Throwable>  $exception
     * @param  array<string, mixed>  $attributes
     */
    private function assertRefused(string $exception, array $attributes): void
    {
        try {
            Layout::create($attributes);
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);

            return;
        }

        $this->fail('the layout row should have been refused');
    }

    private function postgresDatabase(): string
    {
        $file = base_path('.env');
        $dotenv = is_file($file) ? Dotenv::parse((string) file_get_contents($file)) : [];

        return getenv('PORTABILITY_DB_DATABASE') ?: ($dotenv['DB_DATABASE'] ?? 'laravel');
    }
}
