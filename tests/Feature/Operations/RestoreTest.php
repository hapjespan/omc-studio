<?php

namespace Tests\Feature\Operations;

use App\Enums\Role;
use App\Models\User;
use App\Services\Accounts\TotpVerifier;
use App\Services\Operations\BackupService;
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
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_it_refuses_a_folder_without_a_database(): void
    {
        $folder = $this->storage.'/backups/empty';
        File::ensureDirectoryExists($folder);
        File::copy($this->storage.'/app.key', $folder.'/app.key');

        $this->artisan('app:restore', ['folder' => $folder])->assertFailed();
        $this->assertFalse($this->app->maintenanceMode()->active());
    }

    public function test_it_refuses_a_folder_without_a_key_and_leaves_the_live_data_alone(): void
    {
        $folder = app(BackupService::class)->run();
        unlink($folder.'/app.key');
        $this->administrator('after@example.test');

        $this->artisan('app:restore', ['folder' => $folder])->assertFailed();
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
