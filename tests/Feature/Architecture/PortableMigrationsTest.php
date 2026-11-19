<?php

namespace Tests\Feature\Architecture;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SchemaRules;
use Tests\TestCase;

class PortableMigrationsTest extends TestCase
{
    private const PG_SCHEMA = 'portability_test';

    public function test_migrate_fresh_runs_on_sqlite(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertTrue(Schema::hasTable('elements'));
        $this->assertSame([], SchemaRules::tablesWithoutIndex());
    }

    public function test_migrate_fresh_runs_on_postgresql(): void
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
            $this->assertTrue(Schema::connection('portability')->hasTable('elements'));
            $this->assertSame([], SchemaRules::tablesWithoutIndex('portability'));
            $this->assertSame([], $pg->table('pg_indexes')->where('schemaname', self::PG_SCHEMA)->whereRaw("indexdef like '% WHERE %'")->pluck('indexname')->all());
        } finally {
            $pg->statement('drop schema if exists '.self::PG_SCHEMA.' cascade');
        }
    }

    private function postgresDatabase(): string
    {
        $file = base_path('.env');
        $dotenv = is_file($file) ? Dotenv::parse((string) file_get_contents($file)) : [];

        return getenv('PORTABILITY_DB_DATABASE') ?: ($dotenv['DB_DATABASE'] ?? 'laravel');
    }
}
