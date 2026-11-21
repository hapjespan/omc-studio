<?php

namespace App\Services\Exchange\Concerns;

use App\Rules\TrimmedText;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Translation\PotentiallyTranslatedString;

trait CollectsFileErrors
{
    use CollectsErrors;

    private const MOMENT = [1000, 9999];

    /** @param array<string, bool> $seen */
    private function once(string $key, array &$seen): bool
    {
        if (isset($seen[$key])) {
            return false;
        }

        $seen[$key] = true;

        return true;
    }

    private function text(mixed $value, string $location, int $max, bool $required = false): bool
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            return ! $required || $this->flag(false, $location, 'jsonx.required');
        }

        if (! is_string($value)) {
            return $this->flag(false, $location, 'jsonx.not_text');
        }

        $sound = true;

        (new TrimmedText($location, $max))->validate($location, $value, function (string $message) use ($location, &$sound) {
            $sound = false;
            $this->record($location, $message);

            return new PotentiallyTranslatedString($message, app('translator'));
        });

        return $sound;
    }

    private function moment(mixed $value, string $location, bool $required = false): bool
    {
        if ($value === null) {
            return $required ? $this->flag(false, $location, 'jsonx.required') : true;
        }

        return $this->flag($this->moments($value) !== null, $location, 'jsonx.date_invalid');
    }

    private function utc(mixed $value): ?string
    {
        return $this->moments($value)?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function moments(mixed $value): ?DateTimeImmutable
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat(DATE_ATOM, $value) : false;

        return $date !== false && $date->format(DATE_ATOM) === $value && (int) $date->format('Y') >= self::MOMENT[0] && (int) $date->format('Y') <= self::MOMENT[1] ? $date : null;
    }

    private function flag(bool $sound, string $location, string $key): bool
    {
        $sound || $this->fail($location, $key);

        return $sound;
    }
}
