<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BasisPoints implements ValidationRule
{
    public const MAX = 10_000;

    private const PATTERN = '/^(\d+)(?:,(\d{1,2}))?$/';

    public function __construct(private readonly string $label) {}

    public static function parse(string $text): ?int
    {
        if (! preg_match(self::PATTERN, trim($text), $match)) {
            return null;
        }

        $basisPoints = (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');

        return $basisPoints <= self::MAX ? $basisPoints : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) && ! is_int($value) || self::parse((string) $value) === null) {
            $fail(__('validation.basis_points', ['label' => $this->label]));
        }
    }
}
