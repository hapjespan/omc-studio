<?php

namespace App\Support;

final class NameNormaliser
{
    public static function normalise(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
