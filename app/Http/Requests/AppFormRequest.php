<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AppFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->replace($this->trimStrings($this->all()));
    }

    /** @param array<array-key, mixed> $values
     * @return array<array-key, mixed> */
    private function trimStrings(array $values): array
    {
        return array_map(fn ($value) => match (true) {
            is_string($value) => trim($value),
            is_array($value) => $this->trimStrings($value),
            default => $value,
        }, $values);
    }
}
