<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, list<array{list<string>, bool}>> */
    public static function indexes(): array
    {
        return [
            'fiscal_years' => [[['organisation_id', 'year'], true]],
            'owners' => [[['organisation_id', 'name_normalised'], true]],
            'applications' => [[['organisation_id', 'name_normalised'], true]],
            'cost_records' => [
                [['organisation_id', 'code_normalised'], true],
                [['organisation_id', 'archived_at'], false],
                [['owner_id'], false],
                [['application_id'], false],
            ],
            'yearly_figures' => [
                [['cost_record_id', 'fiscal_year_id'], true],
                [['fiscal_year_id'], false],
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
        $this->assertEqualsCanonicalizing(['id', 'organisation_id', 'year', 'created_at', 'updated_at'], Schema::getColumnListing('fiscal_years'));
        $this->assertEqualsCanonicalizing(['id', 'organisation_id', 'name', 'name_normalised', 'supplier', 'created_at', 'updated_at'], Schema::getColumnListing('applications'));
        $this->assertEqualsCanonicalizing(['id', 'organisation_id', 'name', 'name_normalised', 'created_at', 'updated_at'], Schema::getColumnListing('owners'));
        $this->assertEqualsCanonicalizing([
            'id', 'organisation_id', 'code', 'code_normalised', 'name', 'cost_kind', 'supplier', 'owner_id', 'cost_centre',
            'application_id', 'description', 'archived_at', 'created_at', 'updated_at',
        ], Schema::getColumnListing('cost_records'));
        $this->assertEqualsCanonicalizing([
            'id', 'cost_record_id', 'fiscal_year_id', 'budget_cents', 'forecast_cents', 'run_bp', 'change_bp',
            'fte_hundredths', 'reference_date', 'remark', 'created_at', 'updated_at',
        ], Schema::getColumnListing('yearly_figures'));
    }

    public function test_foreign_keys_follow_the_recorded_actions(): void
    {
        $actions = fn (string $table) => collect(Schema::getForeignKeys($table))
            ->mapWithKeys(fn ($fk) => [$fk['columns'][0] => $fk['on_delete']])->sortKeys()->all();

        $this->assertSame(['organisation_id' => 'cascade'], $actions('fiscal_years'));
        $this->assertSame(['organisation_id' => 'cascade'], $actions('owners'));
        $this->assertSame(['organisation_id' => 'cascade'], $actions('applications'));
        $this->assertSame(
            ['application_id' => 'restrict', 'organisation_id' => 'restrict', 'owner_id' => 'restrict'],
            $actions('cost_records'),
        );
        $this->assertSame(
            ['cost_record_id' => 'no action', 'fiscal_year_id' => 'restrict'],
            $actions('yearly_figures'),
        );
    }
}
