<?php

namespace Tests\Feature\Operations;

use Tests\TestCase;

class MakefileTargetsTest extends TestCase
{
    private function makefile(): string
    {
        return (string) file_get_contents(base_path('Makefile'));
    }

    public function test_backup_target_calls_app_backup_in_the_container(): void
    {
        $this->assertMatchesRegularExpression('/^backup:\n\tdocker compose exec app php artisan app:backup$/m', $this->makefile());
    }

    public function test_restore_target_takes_a_folder_and_stops_and_starts_the_application_around_app_restore(): void
    {
        $this->assertMatchesRegularExpression('/^restore:\n.*FOLDER.*\n\tdocker compose stop app\n.*app:restore "\$\(FOLDER\)"\n\tdocker compose start app$/m', $this->makefile());
    }
}
