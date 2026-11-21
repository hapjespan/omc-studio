<?php

namespace Tests\Feature\Audit;

use App\Services\Audit\SecurityLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityLogFileTest extends TestCase
{
    public function test_the_security_log_file_is_readable_by_its_owner_and_group_only(): void
    {
        $directory = sys_get_temp_dir().'/security-log-'.getmypid();
        File::ensureDirectoryExists($directory);
        config(['logging.channels.security.path' => $directory.'/security.log']);
        Log::forgetChannel('security');
        $file = $directory.'/security-'.now()->format('Y-m-d').'.log';

        SecurityLog::record('permission_probe');

        $this->assertSame(0640, fileperms($file) & 0777);
        File::deleteDirectory($directory);
        Log::forgetChannel('security');
    }

    public function test_the_readme_tells_the_operator_to_copy_the_security_logs_with_a_backup(): void
    {
        $this->assertStringContainsString('storage/logs/security-', (string) file_get_contents(base_path('README.md')));
    }
}
