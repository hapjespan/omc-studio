<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class TwoProcessRunner
{
    /**
     * @param  array<int, string>  $arguments
     * @param  array<string, string>  $environment
     * @return array<int, string>
     */
    public function run(string $script, array $arguments, array $environment = [], int $processes = 2): array
    {
        $startAt = microtime(true) + 1.0;
        $started = [];

        for ($number = 0; $number < $processes; $number++) {
            $process = new Process([PHP_BINARY, $script, ...$arguments], base_path(), [...$environment, 'RACE_START_AT' => (string) $startAt], null, 60.0);
            $process->start();
            $started[] = $process;
        }

        return array_map(function (Process $process) use ($script): string {
            $process->wait();

            if (! $process->isSuccessful()) {
                throw new RuntimeException($script.' failed: '.$process->getErrorOutput().$process->getOutput());
            }

            return trim($process->getOutput());
        }, $started);
    }
}
