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
        $partial = DB::table('sqlite_master')->where('type', 'index')->whereRaw("lower(sql) like '% where %'")->pluck('name');

        $this->assertSame([], $partial->all());
    }

    public function test_a_planted_table_without_an_index_is_caught(): void
    {
        Schema::create('planted_without_index', fn ($table) => $table->string('label'));

        $this->assertSame(['planted_without_index'], SchemaRules::tablesWithoutIndex());
    }
}
