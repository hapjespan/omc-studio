<?php

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SchemaRules;
use Tests\TestCase;

class IndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_table_has_at_least_one_index(): void
    {
        $this->assertSame([], SchemaRules::tablesWithoutIndex());
    }

    public function test_no_index_is_partial(): void
    {
        $partial = DB::connection()->getDriverName() === 'pgsql'
            ? DB::table('pg_indexes')->where('schemaname', 'public')->whereRaw("indexdef like '% WHERE %'")->pluck('indexname')
            : DB::table('sqlite_master')->where('type', 'index')->whereRaw("lower(sql) like '% where %'")->pluck('name');

        $this->assertSame([], $partial->all());
    }

    public function test_the_declared_foreign_key_indexes_exist(): void
    {
        $this->assertSame([], SchemaRules::missingIndexes([
            'two_factor_recovery_codes' => ['user_id'],
            'undo_records' => ['user_id'],
        ]));
    }

    public function test_a_primary_key_does_not_count_as_a_declared_index(): void
    {
        Schema::create('planted_primary_only', fn ($table) => $table->id());

        $this->assertSame(['planted_primary_only(id)'], SchemaRules::missingIndexes(['planted_primary_only' => ['id']]));
    }

    public function test_a_planted_table_without_an_index_is_caught(): void
    {
        Schema::create('planted_without_index', fn ($table) => $table->string('label'));

        $this->assertSame(['planted_without_index'], SchemaRules::tablesWithoutIndex());
    }

    public function test_user_id_is_indexed_on_recovery_codes_and_undo_records(): void
    {
        foreach (['two_factor_recovery_codes', 'undo_records'] as $table) {
            $this->assertContains(['user_id'], array_column(Schema::getIndexes($table), 'columns'), $table);
        }
    }
}
