<?php

namespace App\Services\Exchange\Concerns;

trait CollectsErrors
{
    private const MAX_ERRORS = 1000;

    /** @var list<array{location: string, message: string}> */
    private array $errors = [];

    private int $count = 0;

    /** @param array<string, int|string> $replace */
    private function fail(string $location, string $key, array $replace = []): void
    {
        $this->record($location, (string) __($key, ['location' => $location, ...$replace]));
    }

    private function record(string $location, string $message): void
    {
        $this->count++;

        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = ['location' => $location, 'message' => $message];
        }
    }
}
