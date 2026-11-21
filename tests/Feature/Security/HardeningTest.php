<?php

namespace Tests\Feature\Security;

use App\Support\QueryTrace;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    public function test_the_docker_ignore_is_an_allowlist_that_keeps_what_the_dockerfile_copies(): void
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $this->file('.dockerignore'))), fn (string $line) => $line !== '' && ! str_starts_with($line, '#')));
        $allowed = array_map(fn (string $line) => substr($line, 1), array_filter($lines, fn (string $line) => str_starts_with($line, '!')));

        $this->assertSame('*', $lines[0]);

        foreach (['app', 'artisan', 'bootstrap', 'composer.json', 'composer.lock', 'config', 'database', 'docker', 'lang', 'package.json', 'package-lock.json', 'public', 'resources', 'routes', 'storage', 'vite.config.js'] as $needed) {
            $this->assertContains($needed, $allowed, $needed);
        }

        foreach (['.worktrees', '.scratch', '.remember', '.playwright-browsers', 'test-results', '.forbidden-names', 'storage/app', 'database/*.sqlite*'] as $kept) {
            $this->assertTrue(in_array($kept, $lines, true) || ! in_array($kept, $allowed, true), $kept);
        }

        $this->assertContains('storage/app', $lines);
        $this->assertContains('database/*.sqlite*', $lines);
    }
}
