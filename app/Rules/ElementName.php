<?php

namespace App\Rules;

use Closure;

class ElementName extends TrimmedText
{
    public function __construct(?string $label = null)
    {
        parent::__construct($label ?? __('fields.name'), 150);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && str_contains($value, '>')) {
            $fail(__('validation.name_forbidden_sign', ['label' => $this->label]));

            return;
        }

        parent::validate($attribute, $value, $fail);
    }
}
