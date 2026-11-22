<?php

namespace Tests\Unit\Models;

use App\Models\Organisation;
use Faker\Generator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_name_is_unique_ignoring_case_and_surrounding_spaces(): void
    {
        Organisation::factory()->create(['name' => 'Acme Zorg']);

        $this->expectException(UniqueConstraintViolationException::class);

        Organisation::factory()->create(['name' => '  ACME zorg ']);
    }

    public function test_the_visible_name_keeps_its_casing(): void
    {
        $organisation = Organisation::factory()->create(['name' => 'Acme Zorg']);

        $this->assertSame('Acme Zorg', $organisation->fresh()->name);
        $this->assertSame('acme zorg', $organisation->fresh()->name_normalised);
    }

    public function test_the_slug_is_derived_from_the_name_and_unique(): void
    {
        $organisation = Organisation::factory()->create(['name' => 'Gemeente Den Haag & Omstreken']);

        $this->assertSame('gemeente-den-haag-omstreken', $organisation->slug);

        $this->expectException(UniqueConstraintViolationException::class);

        Organisation::factory()->create(['name' => 'Gemeente den Haag - Omstreken']);
    }

    public function test_the_factory_makes_a_separate_organisation_when_faker_starts_over(): void
    {
        $slugs = [];

        for ($i = 0; $i < 200; $i++) {
            $this->app->forgetInstance(Generator::class.':'.config('app.faker_locale'));
            mt_srand(2026);
            $slugs[] = Organisation::factory()->create()->slug;
        }

        $this->assertCount(200, array_unique($slugs));
    }

    public function test_the_archive_state_is_indexed_and_defaults_to_active(): void
    {
        $organisation = Organisation::factory()->create();

        $this->assertNull($organisation->fresh()->archived_at);
        $this->assertTrue(collect(Schema::getIndexes('organisations'))->contains(fn ($i) => $i['columns'] === ['archived_at']));
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'name_normalised', 'slug', 'logo_path', 'is_sample', 'archived_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('organisations'),
        );
    }
}
