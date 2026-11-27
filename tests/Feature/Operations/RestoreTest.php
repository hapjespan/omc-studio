<?php

namespace Tests\Feature\Operations;

use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Accounts\TotpVerifier;
use App\Services\Operations\BackupService;
use App\Services\Operations\RestoreService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class RestoreTest extends TestCase
{
    protected string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Backup and restore copy the SQLite database file, the engine the application is delivered with (ADR 0022).');
        }

        $this->storage = base_path('.scratch/ops-'.Str::random(8));
        mkdir($this->storage.'/app/private/logos', 0755, true);
        mkdir($this->storage.'/framework', 0755, true);
        $this->app->useStoragePath($this->storage);
        $this->useKey('base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher'))));
        file_put_contents($this->storage.'/app/private/logos/a.png', 'logo-one');

        touch($this->storage.'/live.sqlite');
        config(['database.connections.sqlite.database' => $this->storage.'/live.sqlite']);
        DB::purge();
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        isset($this->storage) && File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_it_refuses_a_folder_without_a_database(): void
    {
        $folder = $this->storage.'/backups/empty';
        File::ensureDirectoryExists($folder);
        File::copy($this->storage.'/app.key', $folder.'/app.key');

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain(__('console.restore_incomplete'))->assertFailed();
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    public function test_it_refuses_a_damaged_database(): void
    {
        $folder = $this->backup();
        file_put_contents($folder.'/database.sqlite', str_repeat('garbage', 500));

        $this->refuses($folder, 'console.restore_damaged');
    }

    public function test_it_refuses_a_database_of_another_application(): void
    {
        $folder = $this->backup();
        unlink($folder.'/database.sqlite');
        (new \PDO('sqlite:'.$folder.'/database.sqlite'))->exec('CREATE TABLE other (id INTEGER)');

        $this->refuses($folder, 'console.restore_foreign');
    }

    public function test_it_refuses_a_key_with_the_wrong_format(): void
    {
        $folder = $this->backup();
        file_put_contents($folder.'/app.key', 'base64:'.base64_encode('short'));

        $this->refuses($folder, 'console.restore_key_invalid');
    }

    public function test_it_refuses_a_key_that_cannot_decrypt_the_second_factor_secrets(): void
    {
        $user = $this->administrator('factor@example.test');
        $user->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(), 'two_factor_confirmed_at' => now()])->save();
        $folder = $this->backup();
        file_put_contents($folder.'/app.key', 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher'))));

        $this->refuses($folder, 'console.restore_key_mismatch');
    }

    public function test_it_refuses_a_folder_without_a_key_and_leaves_the_live_data_alone(): void
    {
        $folder = $this->backup();
        unlink($folder.'/app.key');
        $this->administrator('after@example.test');

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain(__('console.restore_incomplete'))->assertFailed();
        $this->assertTrue(User::query()->where('email', 'after@example.test')->exists());
    }

    public function test_the_data_equals_the_backup_after_a_restore(): void
    {
        $this->administrator('before@example.test');
        $folder = app(BackupService::class)->run();
        User::query()->where('email', 'before@example.test')->delete();
        $this->administrator('after@example.test');

        $this->artisan('app:restore', ['folder' => $folder])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'before@example.test')->exists());
        $this->assertFalse(User::query()->where('email', 'after@example.test')->exists());
    }

    public function test_the_uploads_of_the_backup_replace_the_live_uploads(): void
    {
        $folder = app(BackupService::class)->run();
        file_put_contents($this->storage.'/app/private/logos/a.png', 'changed');
        file_put_contents($this->storage.'/app/private/logos/b.png', 'later');

        $this->artisan('app:restore', ['folder' => $folder])->assertSuccessful();

        $this->assertSame('logo-one', file_get_contents($this->storage.'/app/private/logos/a.png'));
        $this->assertFileDoesNotExist($this->storage.'/app/private/logos/b.png');
    }

    public function test_it_removes_the_write_ahead_files_of_the_replaced_database(): void
    {
        $folder = app(BackupService::class)->run();
        DB::disconnect();
        file_put_contents($this->storage.'/live.sqlite-wal', 'stale');
        file_put_contents($this->storage.'/live.sqlite-shm', 'stale');

        $this->artisan('app:restore', ['folder' => $folder])->assertSuccessful();

        $this->assertFileDoesNotExist($this->storage.'/live.sqlite-wal');
        $this->assertFileDoesNotExist($this->storage.'/live.sqlite-shm');
    }

    public function test_a_restore_takes_a_safety_backup_of_the_live_state_first(): void
    {
        $folder = $this->backup();
        $this->administrator('live@example.test');

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain(__('console.restore_restart'))->assertSuccessful();

        $safety = glob($this->storage.'/backups/*');
        $this->assertCount(2, $safety);
    }

    public function test_a_second_factor_from_before_the_backup_accepts_its_code_after_a_restore(): void
    {
        $google = new Google2FA;
        $secret = $google->generateSecretKey();
        $user = $this->administrator('factor@example.test');
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $folder = app(BackupService::class)->run();

        $this->useKey('base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher'))));
        DB::table('users')->where('id', $user->id)->update(['two_factor_secret' => Crypt::encryptString($google->generateSecretKey())]);

        $this->artisan('app:restore', ['folder' => $folder])->assertSuccessful();

        $this->useKey(trim((string) file_get_contents($this->storage.'/app.key')));
        $this->assertTrue(app(TotpVerifier::class)->verify($user->fresh(), $google->getCurrentOtp($secret)));
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    public function test_it_refuses_a_backup_whose_database_references_a_missing_logo(): void
    {
        $this->organisationWithLogo('logos/a.png');
        $folder = $this->backup();
        unlink($folder.'/uploads/logos/a.png');
        $this->administrator('live@example.test');
        file_put_contents($this->storage.'/app/private/logos/a.png', 'live-bytes');
        $liveKey = file_get_contents($this->storage.'/app.key');

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain('logos/a.png')->assertFailed();

        $this->assertTrue(User::query()->where('email', 'live@example.test')->exists());
        $this->assertSame('live-bytes', file_get_contents($this->storage.'/app/private/logos/a.png'));
        $this->assertSame($liveKey, file_get_contents($this->storage.'/app.key'));
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    public function test_a_backup_without_referenced_logos_restores_without_an_uploads_directory(): void
    {
        $folder = $this->backup();
        File::deleteDirectory($folder.'/uploads');

        $this->artisan('app:restore', ['folder' => $folder])->assertSuccessful();

        $this->assertDirectoryExists($this->storage.'/app/private');
        $this->assertFileDoesNotExist($this->storage.'/app/private/logos/a.png');
    }

    public function test_a_failure_during_the_swap_rolls_back_the_complete_previous_installation(): void
    {
        $folder = $this->backup();
        $this->administrator('live@example.test');
        DB::disconnect();
        file_put_contents($this->storage.'/app/private/logos/a.png', 'live-bytes');
        $liveKey = file_get_contents($this->storage.'/app.key');
        $liveDatabase = file_get_contents($this->storage.'/live.sqlite');

        $this->app->bind(RestoreService::class, fn ($app) => new class($app) extends RestoreService
        {
            private int $moves = 0;

            protected function move(string $from, string $to): void
            {
                if (++$this->moves === 4) {
                    throw new \RuntimeException('injected');
                }

                parent::move($from, $to);
            }
        });

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain('automatisch teruggezet')->assertFailed();

        $this->assertSame($liveDatabase, file_get_contents($this->storage.'/live.sqlite'));
        $this->assertSame('live-bytes', file_get_contents($this->storage.'/app/private/logos/a.png'));
        $this->assertSame($liveKey, file_get_contents($this->storage.'/app.key'));
        $this->assertFileDoesNotExist($this->storage.'/live.sqlite.restore');
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    public function test_a_failure_while_staging_leaves_the_live_data_alone(): void
    {
        $folder = $this->backup();
        $this->administrator('live@example.test');
        unlink($folder.'/uploads/logos/a.png');
        symlink('/nonexistent/target', $folder.'/uploads/logos/a.png');

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain('Voorbereiden van het herstel is mislukt')->assertFailed();

        $this->assertTrue(User::query()->where('email', 'live@example.test')->exists());
        $this->assertSame('logo-one', file_get_contents($this->storage.'/app/private/logos/a.png'));
        $this->assertDirectoryDoesNotExist($this->storage.'/app/private.restore');
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    public function test_a_partial_directory_copy_never_replaces_live_uploads(): void
    {
        $this->organisationWithLogo('logos/a.png');
        $folder = $this->backup();
        File::partialMock()->shouldReceive('copyDirectory')
            ->with($folder.'/uploads', \Mockery::type('string'))
            ->once()->andReturn(false);

        $this->artisan('app:restore', ['folder' => $folder])->assertExitCode(3);

        $this->assertSame('logo-one', file_get_contents($this->storage.'/app/private/logos/a.png'));
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    public function test_it_refuses_a_logo_path_that_leaves_the_uploads_directory(): void
    {
        $this->organisationWithLogo('../app.key');
        $folder = $this->backup();

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain('../app.key')->assertFailed();
    }

    public function test_an_empty_installation_restores(): void
    {
        $folder = $this->backup();
        File::deleteDirectory($this->storage.'/app/private');

        $this->artisan('app:restore', ['folder' => $folder])->assertSuccessful();

        $this->assertDirectoryExists($this->storage.'/app/private/logos');
    }

    public function test_an_incomplete_rollback_reports_the_recovery_location(): void
    {
        $folder = $this->backup();
        $this->app->bind(RestoreService::class, fn ($app) => new class($app) extends RestoreService
        {
            private int $moves = 0;

            protected function move(string $from, string $to): void
            {
                if (++$this->moves >= 4) {
                    throw new \RuntimeException('injected');
                }

                parent::move($from, $to);
            }
        });

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain($this->storage.'/recovery/')->assertExitCode(1);

        $this->assertNotEmpty(glob($this->storage.'/recovery/*/*'));
        $this->assertTrue($this->app->maintenanceMode()->active());
    }

    private function organisationWithLogo(string $path): void
    {
        Organisation::factory()->create(['logo_path' => $path]);
    }

    private function backup(): string
    {
        return app(BackupService::class)->run();
    }

    private function refuses(string $folder, string $message): void
    {
        $liveKey = file_get_contents($this->storage.'/app.key');
        $this->administrator('live@example.test');

        $this->artisan('app:restore', ['folder' => $folder])->expectsOutputToContain(__($message))->assertFailed();

        $this->assertTrue(User::query()->where('email', 'live@example.test')->exists());
        $this->assertSame($liveKey, file_get_contents($this->storage.'/app.key'));
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    private function administrator(string $email): User
    {
        return User::factory()->create(['email' => $email, 'role' => Role::Administrator]);
    }

    private function useKey(string $key): void
    {
        file_put_contents($this->storage.'/app.key', $key);
        config(['app.key' => $key]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }
}
