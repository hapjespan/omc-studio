<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TrimmedText implements ValidationRule
{
    public function __construct(protected string $label, protected int $max) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && mb_strlen(trim($value)) > $this->max) {
            $fail(__('validation.text_max', ['label' => $this->label, 'max' => $this->max]));
        }
    }
}
