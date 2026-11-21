<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Format
{
    public const TIMEZONE = 'Europe/Amsterdam';

    public static function formatAmount(int $cents): string
    {
        return self::signed($cents, '€ '.self::group(intdiv(abs($cents), 100)).','.sprintf('%02d', abs($cents) % 100));
    }

    public static function formatWholeEuros(int $cents): string
    {
        return self::signed($cents, '€ '.self::group(intdiv(abs($cents) + 50, 100)));
    }

    public static function formatShare(int $part, int $total): string
    {
        if ($total === 0) {
            return '-';
        }

        $basisPoints = intdiv(abs($part) * 20000 + abs($total), 2 * abs($total));
        $text = sprintf('%d,%02d', intdiv($basisPoints, 100), $basisPoints % 100);

        return self::signed($basisPoints > 0 ? $part * $total : 0, rtrim(rtrim($text, '0'), ',').'%');
    }

    public static function formatFte(int $hundredths): string
    {
        return self::signed($hundredths, intdiv(abs($hundredths), 100).','.sprintf('%02d', abs($hundredths) % 100));
    }

    public static function formatDate(string $instant): string
    {
        return self::local($instant)->format('d-m-Y');
    }

    public static function formatTime(string $instant): string
    {
        return self::local($instant)->format('H:i');
    }

    private static function local(string $instant): DateTimeImmutable
    {
        return (new DateTimeImmutable($instant))->setTimezone(new DateTimeZone(self::TIMEZONE));
    }

    private static function group(int $whole): string
    {
        return number_format($whole, 0, '', '.');
    }

    private static function signed(int $value, string $text): string
    {
        return $value < 0 ? '-'.$text : $text;
    }
}
