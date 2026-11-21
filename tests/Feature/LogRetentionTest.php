<?php

namespace Tests\Feature;

use App\Logging\PruneByAge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogRetentionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/log-retention-'.getmypid();
        File::makeDirectory($this->directory.'/logs', 0777, true, true);
        $this->app->useStoragePath($this->directory);
        config(['logging.channels.security.path' => $this->directory.'/logs/security.log', 'logging.channels.daily.path' => $this->directory.'/logs/laravel.log']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function seedFile(string $name, int $daysOld): string
    {
        $path = $this->directory.'/logs/'.$name.'-'.Carbon::today()->subDays($daysOld)->format('Y-m-d').'.log';
        File::put($path, 'x');

        return $path;
    }

    public function test_security_log_files_older_than_ninety_days_are_removed_by_date(): void
    {
        $old = $this->seedFile('security', 91);
        $edge = $this->seedFile('security', 90);
        $recent = $this->seedFile('security', 3);

        Log::forgetChannel('security');
        Log::channel('security')->info('probe');

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($edge);
        $this->assertFileExists($recent);
    }

    public function test_error_log_files_older_than_fourteen_days_are_removed_by_date(): void
    {
        $old = $this->seedFile('laravel', 15);
        $edge = $this->seedFile('laravel', 14);
        $recent = $this->seedFile('laravel', 1);
        $other = $this->seedFile('security', 15);

        Log::forgetChannel('daily');
        Log::channel('daily')->info('probe');

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($edge);
        $this->assertFileExists($recent);
        $this->assertFileExists($other);
    }

    public function test_pruning_ignores_files_without_a_date(): void
    {
        $file = $this->directory.'/logs/security-notes.log';
        File::put($file, 'x');

        (new PruneByAge)(new \stdClass, 'security', '90');

        $this->assertFileExists($file);
    }
}
