<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class CiWorkflowTest extends TestCase
{
    /** @return list<string> */
    private function playwrightLines(): array
    {
        return array_values(array_filter(explode("\n", (string) file_get_contents(base_path('.github/workflows/ci.yml'))), fn (string $line) => str_contains($line, 'playwright test')));
    }

    public function test_the_performance_and_secure_cookie_runs_go_without_retries(): void
    {
        $lines = array_values(array_filter($this->playwrightLines(), fn (string $line) => ! str_contains($line, '--grep-invert')));

        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $this->assertStringContainsString('--retries=0', $line, trim($line));
        }
    }

    public function test_the_functional_run_keeps_the_retries_of_the_playwright_config(): void
    {
        $lines = array_values(array_filter($this->playwrightLines(), fn (string $line) => str_contains($line, '--grep-invert')));

        $this->assertCount(1, $lines);
        $this->assertStringNotContainsString('--retries', $lines[0]);
        $this->assertStringContainsString('retries: process.env.CI ? 2 : 0', (string) file_get_contents(base_path('playwright.config.ts')));
    }
}
