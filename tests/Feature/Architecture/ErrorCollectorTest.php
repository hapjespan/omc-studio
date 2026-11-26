<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class ErrorCollectorTest extends TestCase
{
    public function test_the_json_import_traits_share_one_error_collector(): void
    {
        $definitions = [];

        foreach (glob(app_path('Services/Exchange/Concerns/*.php')) ?: [] as $file) {
            $source = (string) file_get_contents($file);

            foreach (['private function record(', 'private function fail(', 'private int $count', 'private const MAX_ERRORS'] as $needle) {
                str_contains($source, $needle) && $definitions[$needle][] = basename($file);
            }
        }

        foreach ($definitions as $needle => $files) {
            $this->assertCount(1, $files, $needle.' in '.implode(', ', $files));
        }

        $this->assertNotEmpty($definitions);
    }
}
