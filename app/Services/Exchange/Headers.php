<?php

namespace App\Services\Exchange;

class Headers
{
    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function row(string $group, array $keys): array
    {
        return array_map(fn (string $key) => self::label($group, $key), $keys);
    }

    public static function label(string $group, string $key): string
    {
        return (string) __('exchange.'.$group.'.'.$key);
    }
}
