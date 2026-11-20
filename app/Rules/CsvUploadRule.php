<?php

namespace App\Rules;

use App\Services\Exchange\Csv\CsvReader;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class CsvUploadRule implements ValidationRule
{
    public const MAXIMUM_BYTES = 5_242_880;

    /**
     * @param  list<string>  $columns
     */
    public function __construct(private readonly array $columns) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->accepts($value)) {
            $fail(__('validation.csv_upload', ['columns' => implode(CsvReader::DELIMITER, $this->columns)]));
        }
    }

    private function accepts(mixed $value): bool
    {
        if (! $value instanceof UploadedFile || ! $value->isValid() || $value->getSize() > self::MAXIMUM_BYTES) {
            return false;
        }

        $path = $value->getRealPath();

        if ($path === false || ! self::isText((string) file_get_contents($path))) {
            return false;
        }

        return (new CsvReader($path))->lines()->current() === $this->columns;
    }

    private static function isText(string $bytes): bool
    {
        return mb_check_encoding($bytes, 'UTF-8') && preg_match('/[^\P{Cc}\t\r\n]/u', $bytes) !== 1;
    }
}
