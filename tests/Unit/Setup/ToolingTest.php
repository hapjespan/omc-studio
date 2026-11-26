<?php

namespace Tests\Unit\Setup;

use PHPUnit\Framework\TestCase;

class ToolingTest extends TestCase
{
    private function script(string $name): string
    {
        $scripts = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/composer.json'), true)['scripts'];

        return implode(' ', (array) $scripts[$name]);
    }

    public function test_verify_runs_pint_check_and_larastan(): void
    {
        $verify = $this->script('verify');

        $this->assertStringContainsString('pint --test', $verify);
        $this->assertStringContainsString('phpstan analyse', $verify);
    }

    public function test_test_runs_phpunit_through_artisan(): void
    {
        $this->assertStringContainsString('artisan test', $this->script('test'));
    }

    public function test_larastan_runs_at_level_six(): void
    {
        $neon = (string) file_get_contents(dirname(__DIR__, 3).'/phpstan.neon');

        $this->assertMatchesRegularExpression('/^\s+level: 6$/m', $neon);
    }

    public function test_phpunit_uses_sqlite_in_memory(): void
    {
        $xml = (string) file_get_contents(dirname(__DIR__, 3).'/phpunit.xml');

        $this->assertStringContainsString('name="DB_CONNECTION" value="sqlite"', $xml);
        $this->assertStringContainsString('name="DB_DATABASE" value=":memory:"', $xml);
    }
}
