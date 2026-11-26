<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IntegerRange implements ValidationRule
{
    public function __construct(private readonly string $label, private readonly int $min, private readonly int $max) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value) || $value < $this->min || $value > $this->max) {
            $fail(__('jsonx.range', ['label' => $this->label, 'min' => $this->min, 'max' => $this->max]));
        }
    }
}
