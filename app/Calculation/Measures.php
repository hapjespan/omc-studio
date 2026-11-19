<?php

namespace App\Calculation;

final class Measures
{
    public const NAMES = ['budget', 'forecast', 'run', 'change', 'undetermined'];

    /** @return array<string, int> */
    public static function zero(): array
    {
        return array_fill_keys(self::NAMES, 0);
    }

    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     * @return array<string, int>
     */
    public static function add(array $a, array $b): array
    {
        foreach (self::NAMES as $name) {
            $a[$name] += $b[$name];
        }

        return $a;
    }
}
