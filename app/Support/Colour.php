<?php

namespace App\Support;

class Colour
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/data/default-colours.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function autoText(string $background): string
    {
        $defaults = self::defaults();

        return self::contrast($background, $defaults['ink']) >= self::contrast($background, $defaults['white']) ? $defaults['ink'] : $defaults['white'];
    }

    public static function effectiveFill(string $fill, int $opacity, string $behind): string
    {
        $alpha = $opacity / 100;
        $hex = '#';

        foreach ([1, 3, 5] as $offset) {
            $hex .= sprintf('%02X', (int) floor(hexdec(substr($fill, $offset, 2)) * $alpha + hexdec(substr($behind, $offset, 2)) * (1 - $alpha) + 0.5));
        }

        return $hex;
    }

    public static function contrast(string $a, string $b): float
    {
        $values = [self::luminance($a), self::luminance($b)];

        return (max($values) + 0.05) / (min($values) + 0.05);
    }

    public static function ratioLabel(float $ratio): string
    {
        return number_format(floor($ratio * 10) / 10, 1, ',', '');
    }

    /** @return list<array{background: string, text: string}> */
    public static function defaultPairs(): array
    {
        $pairs = [];

        foreach (self::defaults()['areas'] as $levels) {
            foreach ($levels as $background) {
                if ($background !== null) {
                    $pairs[] = ['background' => $background, 'text' => self::autoText($background)];
                }
            }
        }

        return $pairs;
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $offset) use ($hex) {
            $s = hexdec(substr($hex, $offset, 2)) / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, [1, 3, 5]);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
