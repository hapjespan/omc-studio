<?php

namespace App\Services\Exchange\Csv;

class CsvWriter
{
    private const ENCLOSURE = '"';

    private const SPECIAL = ";\"\n\r";

    private const FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(private readonly mixed $handle) {}

    /**
     * @param  list<string|int|null>  $fields
     */
    public function write(array $fields): void
    {
        $values = array_map(
            fn (string|int|null $field): string => is_int($field) ? (string) $field : self::field((string) $field),
            $fields,
        );

        fwrite($this->handle, implode(CsvReader::DELIMITER, $values)."\n");
    }

    public static function field(string $value): string
    {
        if (in_array(substr($value, 0, 1), self::FORMULA, true)) {
            $value = "'".$value;
        }

        if (strpbrk($value, self::SPECIAL) === false) {
            return $value;
        }

        return self::ENCLOSURE.str_replace(self::ENCLOSURE, self::ENCLOSURE.self::ENCLOSURE, $value).self::ENCLOSURE;
    }

    public static function decimal(int $hundredths): string
    {
        $sign = $hundredths < 0 ? '-' : '';
        $units = intdiv(abs($hundredths), 100);
        $rest = abs($hundredths) % 100;

        return $sign.$units.','.str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    }
}
