<?php

namespace Tests\Feature\Setup;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class CleanStartEvidenceTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = base_path('.scratch/clean-start-test-'.bin2hex(random_bytes(4)));
        mkdir($this->workspace.'/bin', 0777, true);
        foreach (['docker', 'curl'] as $stub) {
            file_put_contents($this->workspace.'/bin/'.$stub, "#!/bin/sh\necho \"\$0 \$@\" >> '".$this->workspace."/calls'\n");
            chmod($this->workspace.'/bin/'.$stub, 0755);
        }
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->workspace]))->run();
        parent::tearDown();
    }

    private function runScript(): Process
    {
        file_put_contents($this->workspace.'/evidence.md', "# Evidence WP1\n");
        $process = new Process(['sh', base_path('scripts/prove-clean-start.sh')], base_path(), [
            'PATH' => $this->workspace.'/bin:'.getenv('PATH'),
            'EVIDENCE_FILE' => $this->workspace.'/evidence.md',
            'PROOF_WORKDIR' => $this->workspace,
        ]);
        $process->run();

        return $process;
    }

    public function test_the_script_builds_starts_and_records_both_times(): void
    {
        $process = $this->runScript();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $evidence = (string) file_get_contents($this->workspace.'/evidence.md');
        $this->assertMatchesRegularExpression('/Build time: \d+ seconds/', $evidence);
        $this->assertMatchesRegularExpression('/First answer of \/ after `docker compose up -d`: \d+ seconds/', $evidence);
        $calls = (string) file_get_contents($this->workspace.'/calls');
        $this->assertStringContainsString('compose build', $calls);
        $this->assertStringContainsString('compose up -d', $calls);
        $this->assertStringContainsString('compose down', $calls);
    }

    public function test_the_script_starts_from_a_clone_with_the_example_environment(): void
    {
        $script = (string) file_get_contents(base_path('scripts/prove-clean-start.sh'));

        $this->assertStringContainsString('git clone', $script);
        $this->assertStringContainsString('cp env.example .env', $script);
        $this->assertStringContainsString('127.0.0.1:8080', $script);
    }

    public function test_the_evidence_file_exists_for_the_host_run(): void
    {
        $this->assertFileExists(base_path('specs/001-omc-studio-v1/wp1-evidence.md'));
    }
}
