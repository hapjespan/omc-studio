<?php

namespace App\Support;

class LayoutLimits
{
    /** @var array{artboard: array{width: int, height: int}, minWidth: int, minHeight: int, directionHeight: int, style: array<string, array{int, int}>}|null */
    private static ?array $limits = null;

    public static function artboardWidth(): int
    {
        return self::all()['artboard']['width'];
    }

    public static function artboardHeight(): int
    {
        return self::all()['artboard']['height'];
    }

    public static function minWidth(): int
    {
        return self::all()['minWidth'];
    }

    public static function minHeight(): int
    {
        return self::all()['minHeight'];
    }

    public static function directionHeight(): int
    {
        return self::all()['directionHeight'];
    }

    /** @return array<string, array{int, int}> */
    public static function style(): array
    {
        return self::all()['style'];
    }

    /** @return array{artboard: array{width: int, height: int}, minWidth: int, minHeight: int, directionHeight: int, style: array<string, array{int, int}>} */
    private static function all(): array
    {
        return self::$limits ??= json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/data/layout-limits.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
