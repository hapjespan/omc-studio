<?php

namespace App\Services\Layout;

class Artboard
{
    public const WIDTH = 1680;

    public const HEIGHT = 1188;

    /** @return array{width: int, height: int} */
    public static function size(): array
    {
        return ['width' => self::WIDTH, 'height' => self::HEIGHT];
    }
}
