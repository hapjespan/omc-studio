<?php

namespace Tests\Feature\Architecture;

use App\Support\FieldLimits;
use App\Support\NameNormaliser;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class FieldLimitsTest extends TestCase
{
    private const LITERALS = [
        '/TrimmedText\(.*, (150|2000|50)\)/',
        "/'max:(150|2000)'/",
        '/(NAME|TEXT|CODE|MAX|NAME_MAX|DESCRIPTION_MAX|REMARK_MAX) = (150|2000|50);/',
        '/\[2000, 2100\]/',
        '/< 2000 \|\| .* > 2100/',
        '/LIMIT = (150|2000);/',
    ];

    /** @return list<string> */
    private function sources(): array
    {
        $files = [];

        foreach (['app', 'resources/js'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)) as $file) {
                if (in_array($file->getExtension(), ['php', 'vue', 'ts'], true) && ! str_ends_with($file->getFilename(), 'FieldLimits.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function test_no_site_inlines_a_field_limit(): void
    {
        $copies = [];

        foreach ($this->sources() as $file) {
            $source = (string) file_get_contents($file);

            foreach (self::LITERALS as $pattern) {
                preg_match($pattern, $source) === 1 && $copies[] = str_replace(base_path().'/', '', $file).' '.$pattern;
            }
        }

        $this->assertSame([], $copies);
    }

    public function test_the_exchange_code_normalises_names_through_one_function(): void
    {
        $inline = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Services/Exchange'), FilesystemIterator::SKIP_DOTS)) as $file) {
            str_contains((string) file_get_contents($file->getPathname()), 'mb_strtolower(trim(') && $inline[] = $file->getFilename();
        }

        $this->assertSame([], $inline);
        $this->assertSame('abc', NameNormaliser::normalise('  AbC '));
    }

    public function test_the_front_end_file_equals_the_php_constants(): void
    {
        $json = json_decode((string) file_get_contents(base_path('resources/data/field-limits.json')), true);

        $this->assertSame(
            ['name' => FieldLimits::NAME, 'text' => FieldLimits::TEXT, 'code' => FieldLimits::CODE, 'yearMin' => FieldLimits::YEAR_MIN, 'yearMax' => FieldLimits::YEAR_MAX],
            $json,
        );
    }
}
