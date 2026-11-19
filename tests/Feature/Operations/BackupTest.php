<?php

namespace Tests\Feature\Operations;

use App\Enums\Role;
use App\Models\User;
use App\Services\Operations\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupTest extends TestCase
{
    protected string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = base_path('.scratch/ops-'.Str::random(8));
        mkdir($this->storage.'/app/private/logos', 0755, true);
        mkdir($this->storage.'/framework', 0755, true);
        $this->app->useStoragePath($this->storage);
        file_put_contents($this->storage.'/app.key', 'base64:'.base64_encode(random_bytes(32)));
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

    public function test_backup_prints_a_dated_folder_holding_database_uploads_and_key(): void
    {
        $this->artisan('app:backup')->assertSuccessful();

        $folders = glob($this->storage.'/backups/*');
        $this->assertCount(1, $folders);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}_\d{6}$/', $folders[0]);
        $this->assertFileExists($folders[0].'/database.sqlite');
        $this->assertSame('logo-one', file_get_contents($folders[0].'/uploads/logos/a.png'));
        $this->assertSame(file_get_contents($this->storage.'/app.key'), file_get_contents($folders[0].'/app.key'));
    }

    public function test_the_database_copy_holds_the_rows_and_stays_readable_while_the_application_writes(): void
    {
        User::factory()->create(['email' => 'one@example.test', 'role' => Role::Administrator]);

        $folder = app(BackupService::class)->run();
        User::factory()->create(['email' => 'two@example.test', 'role' => Role::Administrator]);

        $copy = new \PDO('sqlite:'.$folder.'/database.sqlite');
        $this->assertSame('ok', $copy->query('PRAGMA integrity_check')->fetchColumn());
        $this->assertSame(1, (int) $copy->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function test_two_backups_in_the_same_second_get_different_folders(): void
    {
        $this->travelTo(now());
        $first = app(BackupService::class)->run();
        $second = app(BackupService::class)->run();

        $this->assertNotSame($first, $second);
        $this->assertFileExists($second.'/database.sqlite');
    }

    public function test_a_failed_backup_leaves_no_partial_folder(): void
    {
        unlink($this->storage.'/app.key');

        try {
            app(BackupService::class)->run();
            $this->fail('expected a failure');
        } catch (\Throwable) {
            $this->assertSame([], glob($this->storage.'/backups/*'));
        }
    }
}
