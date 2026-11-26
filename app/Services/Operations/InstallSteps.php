<?php

namespace App\Services\Operations;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class InstallSteps
{
    /** @var list<callable> */
    private array $steps = [];

    public function add(callable $step): void
    {
        $this->steps[] = $step;
    }

    public function run(OutputInterface $output = new NullOutput): void
    {
        foreach ($this->steps as $step) {
            $step($output);
        }
    }
}
