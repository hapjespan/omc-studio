<?php

namespace Tests\Unit\Setup;

use Tests\TestCase;

class DatabaseSchemaConfigTest extends TestCase
{
    public function test_the_postgres_search_path_defaults_to_public(): void
    {
        if (getenv('DB_SCHEMA') !== false) {
            $this->markTestSkipped('DB_SCHEMA is set for a throwaway schema run');
        }

        $this->assertSame('public', config('database.connections.pgsql.search_path'));
    }

    public function test_the_postgres_search_path_follows_db_schema_for_a_throwaway_schema(): void
    {
        putenv('DB_SCHEMA=ci_scratch');

        try {
            $config = require base_path('config/database.php');
        } finally {
            putenv('DB_SCHEMA');
        }

        $this->assertSame('ci_scratch', $config['connections']['pgsql']['search_path']);
    }
}
