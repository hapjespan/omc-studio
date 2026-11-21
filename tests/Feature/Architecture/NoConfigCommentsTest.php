<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class NoConfigCommentsTest extends TestCase
{
    public function test_config_files_carry_no_comments(): void
    {
        $commented = [];

        foreach (glob(config_path('*.php')) ?: [] as $file) {
            $tokens = token_get_all((string) file_get_contents($file));

            if (collect($tokens)->contains(fn ($token) => is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true))) {
                $commented[] = basename($file);
            }
        }

        $this->assertSame([], $commented);
    }
}
