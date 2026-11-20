<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HexColour implements ValidationRule
{
    public function __construct(private readonly string $label) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1) {
            $fail(__('jsonx.colour', ['label' => $this->label]));
        }
    }
}
