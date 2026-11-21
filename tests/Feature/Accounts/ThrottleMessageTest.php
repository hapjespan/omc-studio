<?php

namespace Tests\Feature\Accounts;

use Tests\TestCase;

class ThrottleMessageTest extends TestCase
{
    public function test_one_second_reads_in_the_singular(): void
    {
        $this->assertSame('Te veel pogingen. Probeer het over 1 seconde opnieuw', trans_choice('auth.throttled', 1, ['seconds' => 1]));
        $this->assertSame('Te veel pogingen. Probeer het over 30 seconden opnieuw', trans_choice('auth.throttled', 30, ['seconds' => 30]));
    }

    public function test_every_caller_chooses_the_plural_form(): void
    {
        $plain = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_contains((string) file_get_contents($file->getPathname()), "__('auth.throttled'")) {
                $plain[] = $file->getFilename();
            }
        }

        $this->assertSame([], $plain);
    }
}
