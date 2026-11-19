<?php

namespace Tests\Unit\Rules;

use App\Rules\CsvUploadRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CsvUploadRuleTest extends TestCase
{
    public function test_a_file_rejected_by_the_php_size_limit_gets_the_size_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $file = new UploadedFile($path, 'import.csv', 'text/csv', UPLOAD_ERR_INI_SIZE, true);

        $errors = Validator::make(['file' => $file], ['file' => [new CsvUploadRule(['code'])]])->errors()->get('file');

        $this->assertSame(['Bestand: kies een CSV-bestand van maximaal 5 MB met de kolommen code'], $errors);
        unlink($path);
    }
}
