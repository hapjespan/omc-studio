<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Password implements ValidationRule
{
    private const MIN = 12;

    private const MAX = 128;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $length = is_string($value) ? mb_strlen($value) : 0;

        if ($length < self::MIN) {
            $fail(__('validation.password_min', ['min' => self::MIN]));
        } elseif ($length > self::MAX) {
            $fail(__('validation.password_max', ['max' => self::MAX]));
        } elseif (isset(self::common()[mb_strtolower((string) $value)])) {
            $fail(__('validation.password_common'));
        }
    }

    /** @return array<string, true> */
    private static function common(): array
    {
        static $list;

        return $list ??= array_fill_keys(
            array_map('mb_strtolower', file(resource_path('data/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
            true,
        );
    }
}
