<?php

namespace Tests\Feature\Database;

use App\Models\Element;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AllocationSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, list<array{list<string>, bool}>> */
    public static function indexes(): array
    {
        return [
            'allocation_sets' => [
                [['canvas_id', 'fiscal_year_id', 'cost_record_id'], true],
                [['cost_record_id'], false],
            ],
            'allocation_lines' => [
                [['allocation_set_id', 'element_id'], true],
                [['element_id'], false],
            ],
            'finalised_years' => [
                [['canvas_id', 'fiscal_year_id'], true],
                [['fiscal_year_id'], false],
            ],
            'system_notices' => [
                [['fingerprint', 'seen_at'], false],
                [['seen_at'], false],
                [['kind', 'last_occurred_at'], false],
                [['canvas_id'], false],
            ],
        ];
    }

    public function test_every_index_of_the_data_model_exists_on_this_engine(): void
    {
        foreach (self::indexes() as $table => $expected) {
            $indexes = collect(Schema::getIndexes($table));

            foreach ($expected as [$columns, $unique]) {
                $this->assertTrue(
                    $indexes->contains(fn ($i) => $i['columns'] === $columns && $i['unique'] === $unique),
                    $table.': '.implode(',', $columns),
                );
            }
        }
    }

    public function test_no_index_is_partial_or_an_expression(): void
    {
        foreach (array_keys(self::indexes()) as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertNotEmpty($index['columns'], $table.' '.$index['name']);
            }
        }
    }

    public function test_the_columns_are_exactly_those_of_the_data_model(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'canvas_id', 'cost_record_id', 'fiscal_year_id', 'created_at', 'updated_at'],
            Schema::getColumnListing('allocation_sets'),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'allocation_set_id', 'element_id', 'basis_points', 'position'],
            Schema::getColumnListing('allocation_lines'),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'canvas_id', 'fiscal_year_id', 'finalised_at', 'finalised_by', 'imported', 'deleted_user_number'],
            Schema::getColumnListing('finalised_years'),
        );
        $this->assertEqualsCanonicalizing([
            'id', 'kind', 'fingerprint', 'canvas_id', 'details', 'occurrences',
            'first_occurred_at', 'last_occurred_at', 'seen_at',
        ], Schema::getColumnListing('system_notices'));
    }

    public function test_foreign_keys_follow_the_recorded_actions(): void
    {
        $actions = fn (string $table) => collect(Schema::getForeignKeys($table))
            ->mapWithKeys(fn ($fk) => [$fk['columns'][0] => $fk['on_delete']])->sortKeys()->all();

        $this->assertSame(
            ['canvas_id' => 'cascade', 'cost_record_id' => 'no action', 'fiscal_year_id' => 'restrict'],
            $actions('allocation_sets'),
        );
        $this->assertSame(
            ['allocation_set_id' => 'cascade', 'element_id' => 'no action'],
            $actions('allocation_lines'),
        );
        $this->assertSame(
            ['canvas_id' => 'cascade', 'finalised_by' => 'set null', 'fiscal_year_id' => 'restrict'],
            $actions('finalised_years'),
        );
        $this->assertSame(
            ['canvas_id' => 'cascade'],
            $actions('system_notices'),
        );
    }

    public function test_deleting_a_canvas_removes_its_elements_sets_and_lines_in_one_statement_on_sqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('PostgreSQL fires the cascades in constraint creation order; the service deletes lines first (ADR 0041)');
        }

        $element = $this->allocate();

        DB::table('canvases')->where('id', $element->canvas_id)->delete();

        $this->assertSame(0, DB::table('elements')->count());
        $this->assertSame(0, DB::table('allocation_sets')->count());
        $this->assertSame(0, DB::table('allocation_lines')->count());
    }

    public function test_deleting_lines_before_the_canvas_works_on_every_engine(): void
    {
        $element = $this->allocate();

        DB::table('allocation_lines')->delete();
        DB::table('canvases')->where('id', $element->canvas_id)->delete();

        $this->assertSame(0, DB::table('elements')->count());
        $this->assertSame(0, DB::table('allocation_sets')->count());
    }

    public function test_deleting_an_element_that_still_carries_a_line_fails(): void
    {
        $element = $this->allocate();

        $this->expectException(QueryException::class);

        DB::table('elements')->where('id', $element->id)->delete();
    }

    public function test_a_notice_keeps_its_canvas_optional_and_details_as_json(): void
    {
        DB::table('system_notices')->insert([
            'kind' => 'closing_check_failed',
            'fingerprint' => 'closing_check_failed:1:2026',
            'canvas_id' => null,
            'details' => json_encode(['difference_cents' => 125]),
            'first_occurred_at' => now(),
            'last_occurred_at' => now(),
        ]);

        $notice = DB::table('system_notices')->sole();

        $this->assertSame(1, (int) $notice->occurrences);
        $this->assertSame(['difference_cents' => 125], json_decode($notice->details, true));
        $this->assertNull($notice->seen_at);
    }

    private function allocate(): Element
    {
        $element = Element::factory()->create();
        $organisationId = $element->canvas->organisation_id;

        $fiscalYearId = DB::table('fiscal_years')->insertGetId([
            'organisation_id' => $organisationId,
            'year' => 2026,
        ]);
        $ownerId = DB::table('owners')->insertGetId([
            'organisation_id' => $organisationId,
            'name' => 'Owner',
            'name_normalised' => 'owner',
        ]);
        $costRecordId = (string) Str::uuid();
        DB::table('cost_records')->insert([
            'id' => $costRecordId,
            'organisation_id' => $organisationId,
            'name' => 'Record',
            'cost_kind' => 'other',
            'owner_id' => $ownerId,
        ]);
        $setId = DB::table('allocation_sets')->insertGetId([
            'canvas_id' => $element->canvas_id,
            'cost_record_id' => $costRecordId,
            'fiscal_year_id' => $fiscalYearId,
        ]);
        DB::table('allocation_lines')->insert([
            'allocation_set_id' => $setId,
            'element_id' => $element->id,
            'basis_points' => 10000,
            'position' => 0,
        ]);

        return $element;
    }
}
