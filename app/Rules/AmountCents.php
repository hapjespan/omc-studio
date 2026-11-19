<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AmountCents implements ValidationRule
{
    public const MAX_CENTS = 99_999_999_999;

    private const PATTERN = '/^(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?$/';

    public function __construct(private readonly string $label) {}

    public static function parse(string $text): ?int
    {
        $text = trim($text);

        if (! preg_match(self::PATTERN, $text, $match)) {
            return null;
        }

        $cents = (int) str_replace('.', '', explode(',', $text)[0]) * 100 + (int) str_pad($match[1] ?? '', 2, '0');

        return $cents <= self::MAX_CENTS ? $cents : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || self::parse($value) === null) {
            $fail(__('validation.amount_cents', ['label' => $this->label]));
        }
    }
}
