<?php

namespace Tests\Unit\Setup;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class CiWorkflowTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function parse(string $path): array
    {
        return (array) Yaml::parseFile(dirname(__DIR__, 3).'/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    private function job(string $name): array
    {
        $jobs = (array) $this->parse('.github/workflows/ci.yml')['jobs'];

        $this->assertArrayHasKey($name, $jobs);

        return (array) $jobs[$name];
    }

    private function commands(string $name): string
    {
        $runs = array_map(
            static fn (array $step): string => (string) ($step['run'] ?? ''),
            (array) $this->job($name)['steps'],
        );

        return implode("\n", $runs);
    }

    public function test_every_pillar_of_the_pipeline_has_its_own_job(): void
    {
        $jobs = (array) $this->parse('.github/workflows/ci.yml')['jobs'];

        $this->assertSame(
            ['php-sqlite', 'php-postgres', 'js', 'scope', 'e2e', 'e2e-performance', 'audit', 'image'],
            array_keys($jobs),
        );
    }

    public function test_the_functional_end_to_end_job_leaves_the_performance_specs_to_their_own_job(): void
    {
        $this->assertStringContainsString("--grep-invert '@performance|Secure session cookie'", $this->commands('e2e'));
    }

    public function test_the_performance_specs_run_alone_with_one_worker_and_no_retries(): void
    {
        $commands = $this->commands('e2e-performance');

        $this->assertStringContainsString("--grep '@performance' --workers=1 --retries=0", $commands);
        $this->assertStringNotContainsString('--grep-invert', $commands);
    }

    public function test_a_pull_request_that_only_touches_documentation_skips_both_playwright_jobs(): void
    {
        foreach (['e2e', 'e2e-performance'] as $name) {
            $job = $this->job($name);

            $this->assertSame('scope', $job['needs']);
            $this->assertSame("needs.scope.outputs.documentation_only != 'true'", $job['if']);
        }
    }

    public function test_a_push_to_release_always_counts_as_a_code_change(): void
    {
        $this->assertStringContainsString('if [ "${{ github.event_name }}" = "pull_request" ]', $this->commands('scope'));
    }

    public function test_the_second_run_uses_a_reachable_postgres_with_the_project_database(): void
    {
        $job = $this->job('php-postgres');
        $service = (array) $job['services']['postgres'];

        $this->assertSame('omc-studio', $service['env']['POSTGRES_DB']);
        $this->assertSame('pgsql', $job['env']['DB_CONNECTION']);
        $this->assertStringContainsString('artisan migrate', $this->commands('php-postgres'));
    }

    public function test_the_first_run_keeps_the_sqlite_connection_of_phpunit(): void
    {
        $this->assertArrayNotHasKey('DB_CONNECTION', (array) ($this->job('php-sqlite')['env'] ?? []));
    }

    public function test_playwright_installs_the_three_engines(): void
    {
        $commands = $this->commands('e2e');

        $this->assertMatchesRegularExpression(
            '/playwright install .*chromium firefox webkit/',
            $commands,
        );
        $this->assertStringContainsString('playwright test', $commands);
    }

    public function test_the_browser_download_is_retried_a_bounded_number_of_times(): void
    {
        $this->assertMatchesRegularExpression('/until npx playwright install .*chromium firefox webkit; do.*-ge 3.*exit 1/s', $this->commands('e2e'));
    }

    public function test_both_audits_run(): void
    {
        $commands = $this->commands('audit');

        $this->assertStringContainsString('composer audit', $commands);
        $this->assertStringContainsString('node scripts/check-audit.mjs', $commands);
    }

    public function test_the_end_to_end_job_runs_under_a_virtual_display_for_the_drawing_engine(): void
    {
        $this->assertStringContainsString('xvfb-run -a npx playwright test', $this->commands('e2e'));
    }

    public function test_the_static_checks_run_over_the_final_build_and_report_a_skip(): void
    {
        $commands = $this->commands('js');

        $this->assertLessThan(strpos($commands, 'npm run check'), strpos($commands, 'npm run build'));
        $this->assertStringContainsString('npm run check', $commands);
        $this->assertStringNotContainsString('| tee', $commands);

        $step = collect((array) $this->job('js')['steps'])->first(fn ($step): bool => (((array) $step)['run'] ?? '') === 'npm run check');
        $this->assertSame('forbidden names', ((array) ((array) $step)['env'])['ALLOWED_SKIPS']);
    }

    public function test_no_step_pipes_a_command_without_pipefail(): void
    {
        foreach ((array) $this->parse('.github/workflows/ci.yml')['jobs'] as $name => $job) {
            foreach ((array) ((array) $job)['steps'] as $step) {
                $step = (array) $step;
                $run = (string) ($step['run'] ?? '');
                $bare = (string) preg_replace('/\'[^\']*\'|"[^"]*"/', '', $run);
                $piped = preg_match('/(?<!\|)\|(?!\|)/', $bare) === 1;
                $safe = ($step['shell'] ?? '') === 'bash' || str_contains($run, 'pipefail');

                $this->assertTrue(! $piped || $safe, sprintf('Job "%s" pipes without pipefail: %s', $name, trim($run)));
            }
        }
    }

    public function test_the_static_checks_run(): void
    {
        $this->assertStringContainsString('npm run check', $this->commands('js'));
    }

    public function test_the_delivered_image_is_built(): void
    {
        $this->assertStringContainsString('docker build', $this->commands('image'));
    }

    public function test_no_step_tolerates_its_own_failure(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__, 3).'/.github/workflows/ci.yml');

        $this->assertStringNotContainsString('continue-on-error', $workflow);
    }

    public function test_every_job_has_a_timeout_and_the_browser_jobs_get_the_longest(): void
    {
        $jobs = (array) $this->parse('.github/workflows/ci.yml')['jobs'];

        $this->assertNotEmpty($jobs);

        foreach ($jobs as $name => $job) {
            $limit = ((array) $job)['timeout-minutes'] ?? null;
            $expected = in_array($name, ['e2e', 'e2e-performance'], true) ? 90 : 30;

            $this->assertSame($expected, $limit, "job {$name} needs timeout-minutes {$expected}");
        }
    }

    public function test_dependabot_only_reports(): void
    {
        $updates = (array) $this->parse('.github/dependabot.yml')['updates'];

        $this->assertNotEmpty($updates);

        foreach ($updates as $update) {
            $this->assertSame(
                0,
                $update['open-pull-requests-limit'] ?? null,
                (string) $update['package-ecosystem'],
            );
        }
    }
}
