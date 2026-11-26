<?php

namespace App\Http\Requests;

class AllocationRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        $lines = $this->input('lines');

        return is_array($lines) ? array_values(array_map(fn (mixed $line) => is_array($line) ? $line : [], $lines)) : [];
    }
}
