<?php

namespace App\Services\Exchange\Concerns;

use App\Rules\ElementName;
use App\Rules\IntegerRange;
use App\Rules\TrimmedText;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

trait ChecksFields
{
    private const MAX_ERRORS = 1000;

    /** @var list<array{location: string, message: string}> */
    private array $errors = [];

    private int $count = 0;

    /** @var list<int> */
    private array $years = [];

    /** @param array<string, bool> $seen */
    private function year(mixed $value, string $location, array &$seen): void
    {
        if (! in_array($value, $this->years, true)) {
            $this->fail($location, 'jsonx.year_unknown');

            return;
        }

        $this->unique($value, $location, $seen);
    }

    /** @param array<mixed, mixed> $node */
    private function text(array $node, string $key, string $location, int $max, bool $required = false, bool $isName = false): ?string
    {
        return $this->value($node[$key] ?? null, $location, $max, $required, $isName);
    }

    private function value(mixed $value, string $location, int $max, bool $required = false, bool $isName = false): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            if ($required) {
                $this->fail($location, 'jsonx.required');
            }

            return null;
        }

        if (! is_string($value)) {
            $this->fail($location, 'jsonx.not_text');

            return null;
        }

        $this->rule($isName ? new ElementName($location) : new TrimmedText($location, $max), $location, $value);

        return $value;
    }

    /** @param array<mixed, mixed> $node */
    private function integer(array $node, string $key, string $location, int $min, int $max, bool $required = false): ?int
    {
        $value = $node[$key] ?? null;

        if ($value === null) {
            if ($required) {
                $this->fail($location, 'jsonx.required');
            }

            return null;
        }

        $this->rule(new IntegerRange($location, $min, $max), $location, $value);

        return is_int($value) ? $value : null;
    }

    /** @param list<string> $allowed */
    private function oneOf(mixed $value, string $location, array $allowed): ?string
    {
        if (is_string($value) && in_array($value, $allowed, true)) {
            return $value;
        }

        $this->fail($location, 'jsonx.unknown_value');

        return null;
    }

    private function date(mixed $value, string $location, ?string $format = null, bool $required = false): void
    {
        if ($value === null) {
            if ($required) {
                $this->fail($location, 'jsonx.required');
            }

            return;
        }

        $sound = is_string($value) && $this->matches($value, $format ?? DATE_ATOM);

        if (! $sound) {
            $this->fail($location, 'jsonx.date_invalid');
        }
    }

    private function matches(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat($format === DATE_ATOM ? $format : '!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    /** @return array<mixed, mixed> */
    private function object(mixed $value, string $location): array
    {
        if (is_array($value) && ($value === [] || ! array_is_list($value))) {
            return $value;
        }

        $this->fail($location, 'jsonx.not_object');

        return [];
    }

    /** @return list<mixed> */
    private function listOf(mixed $value, string $location): array
    {
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        $this->fail($location, 'jsonx.not_list');

        return [];
    }

    /** @param array<string, bool> $seen */
    private function unique(mixed $value, string $location, array &$seen, string $key = 'jsonx.duplicate'): bool
    {
        $hash = is_array($value) ? serialize($value) : gettype($value).':'.$value;

        if (isset($seen[$hash])) {
            $this->fail($location, $key);

            return false;
        }

        $seen[$hash] = true;

        return true;
    }

    private function rule(ValidationRule $rule, string $location, mixed $value): void
    {
        $rule->validate($location, $value, function (string $message) use ($location) {
            $this->record($location, $message);

            return new PotentiallyTranslatedString($message, app('translator'));
        });
    }

    /** @param array<string, int|string> $replace */
    private function fail(string $location, string $key, array $replace = []): void
    {
        $this->record($location, __($key, ['location' => $location, ...$replace]));
    }

    private function record(string $location, string $message): void
    {
        $this->count++;

        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = ['location' => $location, 'message' => $message];
        }
    }
}
