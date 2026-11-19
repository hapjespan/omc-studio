<?php

namespace App\Services\Structure;

use App\Models\Element;
use Illuminate\Validation\ValidationException;

class UnknownAllocationGuard
{
    public function assertNone(mixed ...$elements): void
    {
        foreach ($elements as $element) {
            if ($element instanceof Element && $element->is_unknown_allocation) {
                throw ValidationException::withMessages(['rule' => __('rule.unknown_allocation_managed')]);
            }
        }
    }
}
