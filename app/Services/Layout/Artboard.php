<?php

namespace App\Services\Layout;

use App\Support\LayoutLimits;

class Artboard
{
    /** @return array{width: int, height: int} */
    public static function size(): array
    {
        return ['width' => LayoutLimits::artboardWidth(), 'height' => LayoutLimits::artboardHeight()];
    }
}
