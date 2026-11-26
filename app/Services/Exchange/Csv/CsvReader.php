<?php

namespace App\Services\Exchange\Csv;

use Generator;

class CsvReader
{
    public const DELIMITER = ';';

    private const ENCLOSURE = '"';

    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    public function __construct(private readonly string $path) {}

    /**
     * @return Generator<int, list<string>>
     */
    public function lines(): Generator
    {
        $handle = @fopen($this->path, 'rb');

        if ($handle === false) {
            return;
        }

        $number = 0;
        $first = true;

        try {
            while (($fields = fgetcsv($handle, 0, self::DELIMITER, self::ENCLOSURE, '')) !== false) {
                $start = $number + 1;
                $number = $start + self::breaks($fields);

                if ($fields === [null]) {
                    continue;
                }

                $values = array_map(fn (?string $field): string => self::unprotected((string) $field), $fields);

                if ($first) {
                    $values[0] = self::stripped($values[0]);
                    $first = false;
                }

                yield $start => $values;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<?string>  $fields
     */
    private static function breaks(array $fields): int
    {
        return array_sum(array_map(fn (?string $field): int => substr_count((string) $field, "\n"), $fields));
    }

    private static function unprotected(string $field): string
    {
        return strlen($field) > 1 && $field[0] === "'" && in_array($field[1], CsvWriter::FORMULA, true) ? substr($field, 1) : $field;
    }

    private static function stripped(string $field): string
    {
        return str_starts_with($field, self::BYTE_ORDER_MARK) ? substr($field, strlen(self::BYTE_ORDER_MARK)) : $field;
    }
}
