<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class CiWorkflowTest extends TestCase
{
    public function test_every_playwright_run_in_ci_goes_without_retries(): void
    {
        $lines = array_values(array_filter(explode("\n", (string) file_get_contents(base_path('.github/workflows/ci.yml'))), fn (string $line) => str_contains($line, 'playwright test')));

        $this->assertNotEmpty($lines);

        foreach ($lines as $line) {
            $this->assertStringContainsString('--retries=0', $line, trim($line));
        }
    }
}
