<?php

namespace Tests\Feature\Operations;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MakefileTargetsTest extends TestCase
{
    private function makefile(): string
    {
        return (string) file_get_contents(base_path('Makefile'));
    }

    public function test_backup_target_calls_app_backup_in_the_container(): void
    {
        $this->assertMatchesRegularExpression('/^backup:\n\tdocker compose exec -u www-data app php artisan app:backup$/m', $this->makefile());
    }

    public function test_restore_restarts_only_after_success_or_a_safe_refusal(): void
    {
        $dir = sys_get_temp_dir().'/omc-make-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700);

        try {
            file_put_contents($dir.'/docker', <<<'SH'
                #!/bin/sh
                echo "$*" >> "$CALLS"
                if [ "$2" = run ]; then exit "$RESTORE_STATUS"; fi
                SH);
            chmod($dir.'/docker', 0700);

            foreach ([0, 3, 1, 137] as $status) {
                $calls = $dir.'/calls-'.$status;
                $make = new Process(['make', 'restore', 'FOLDER=example'], base_path(), [
                    'PATH' => $dir.':'.getenv('PATH'),
                    'CALLS' => $calls,
                    'RESTORE_STATUS' => (string) $status,
                ]);
                $make->run();
                $expected = ['compose stop app', 'compose run --rm --no-deps --entrypoint  -u www-data app php artisan app:restore example'];
                if (in_array($status, [0, 3], true)) {
                    $expected[] = 'compose start app';
                }
                $this->assertSame($status === 0, $make->isSuccessful());
                $this->assertSame($expected, file($calls, FILE_IGNORE_NEW_LINES));
            }
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
