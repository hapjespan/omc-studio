<?php

namespace App\Services\Operations;

class InstallSteps
{
    /** @var list<callable> */
    private array $steps = [];

    public function add(callable $step): void
    {
        $this->steps[] = $step;
    }

    public function run(): void
    {
        foreach ($this->steps as $step) {
            $step();
        }
    }
}
