<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AppFormRequest extends FormRequest
{
    private const NEVER_TRIMMED = ['current_password', 'password', 'password_confirmation'];

    protected function prepareForValidation(): void
    {
        $this->replace($this->trimStrings($this->all()));
    }

    /** @param array<array-key, mixed> $values
     * @return array<array-key, mixed> */
    private function trimStrings(array $values): array
    {
        $trimmed = [];

        foreach ($values as $key => $value) {
            $trimmed[$key] = match (true) {
                in_array($key, self::NEVER_TRIMMED, true) => $value,
                is_string($value) => trim($value),
                is_array($value) => $this->trimStrings($value),
                default => $value,
            };
        }

        return $trimmed;
    }
}
