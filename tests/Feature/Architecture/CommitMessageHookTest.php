<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CommitMessageHookTest extends TestCase
{
    private const HOOK = __DIR__.'/../../../.githooks/commit-msg';

    private function run_hook(string $message): int
    {
        $file = tempnam(sys_get_temp_dir(), 'commit-message-');
        file_put_contents($file, $message);

        $process = new Process(['sh', self::HOOK, $file]);
        $process->run();
        unlink($file);

        return (int) $process->getExitCode();
    }

    private function trailer(): string
    {
        return 'Co-Authored'.'-By: Someone <someone@example.test>';
    }

    private function session(): string
    {
        return 'https://cl'.'aude.ai/code/session_017vppHL1fSe3W7UJW6hLefU';
    }

    public function test_the_hook_is_tracked_and_executable(): void
    {
        $this->assertFileExists(self::HOOK);
        $this->assertTrue(is_executable(self::HOOK));
    }

    public function test_a_normal_conventional_commit_is_accepted(): void
    {
        $this->assertSame(0, $this->run_hook("fix: carry over skips archived cost records\n\nThe decision is in the constitution.\n"));
    }

    public function test_a_comment_line_of_the_commit_template_is_ignored(): void
    {
        $this->assertSame(0, $this->run_hook("feat: add a thing\n\n# ".$this->trailer()."\n"));
    }

    public function test_the_trigger_text_inside_a_sentence_is_accepted(): void
    {
        $this->assertSame(0, $this->run_hook("docs: explain the rule\n\nThe hook refuses a line that starts with ".substr($this->trailer(), 0, 12)." and a session link.\n"));
    }

    /** @return array<string, array{string}> */
    public static function refusals(): array
    {
        return [
            'trailer' => ['trailer'],
            'trailer in lower case' => ['lower'],
            'trailer in upper case' => ['upper'],
            'session link' => ['session'],
            'session trailer' => ['sessionTrailer'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_a_trailer_or_a_session_link_is_refused(string $kind): void
    {
        $line = match ($kind) {
            'trailer' => $this->trailer(),
            'lower' => strtolower($this->trailer()),
            'upper' => strtoupper($this->trailer()),
            'session' => 'See '.$this->session(),
            'sessionTrailer' => 'Cl'.'aude-Session: '.$this->session(),
            default => throw new \InvalidArgumentException($kind),
        };

        $this->assertSame(1, $this->run_hook("chore: something\n\nBody text.\n\n".$line."\n"));
    }
}
