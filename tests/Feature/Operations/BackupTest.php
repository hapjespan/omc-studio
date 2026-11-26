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

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Backup and restore copy the SQLite database file, the engine the application is delivered with (ADR 0022).');
        }

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
        isset($this->storage) && File::deleteDirectory($this->storage);

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

    private function organisationWithLogo(string $path): void
    {
        DB::table('organisations')->insert(['name' => 'Org', 'logo_path' => $path, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function bindCopyHook(callable $hook): void
    {
        $this->app->bind(BackupService::class, fn () => new class($hook) extends BackupService
        {
            public int $calls = 0;

            public function __construct(private $hook) {}

            protected function copyUploads(string $from, string $to): void
            {
                $this->calls++;
                ($this->hook)($this->calls);
                parent::copyUploads($from, $to);
            }
        });
    }

    private function replaceLogoOnFirstCopy(): void
    {
        $this->organisationWithLogo('logos/a.png');
        file_put_contents($this->storage.'/app/private/logos/b.png', 'logo-two');

        $this->bindCopyHook(function (int $call) {
            if ($call === 1) {
                DB::table('organisations')->update(['logo_path' => 'logos/b.png']);
                unlink($this->storage.'/app/private/logos/a.png');
            }
        });
    }

    public function test_a_logo_replaced_between_the_database_and_upload_copy_is_retried_from_a_fresh_database_copy(): void
    {
        $this->replaceLogoOnFirstCopy();

        $folder = app(BackupService::class)->run();

        $this->assertNull(BackupService::missingLogo($folder));
        $this->assertSame('logo-two', file_get_contents($folder.'/uploads/logos/b.png'));
        $copy = new \PDO('sqlite:'.$folder.'/database.sqlite');
        $this->assertSame('logos/b.png', $copy->query('SELECT logo_path FROM organisations')->fetchColumn());
    }

    public function test_a_retried_backup_restores_with_its_logo(): void
    {
        $this->replaceLogoOnFirstCopy();
        $folder = app(BackupService::class)->run();
        unlink($this->storage.'/app/private/logos/b.png');

        $this->artisan('app:restore', ['folder' => $folder])->assertSuccessful();

        $this->assertSame('logo-two', file_get_contents($this->storage.'/app/private/logos/b.png'));
    }

    public function test_a_persistent_inconsistency_fails_cleans_up_and_keeps_earlier_backups_usable(): void
    {
        $good = app(BackupService::class)->run();
        $this->organisationWithLogo('logos/a.png');
        $this->bindCopyHook(function () {
            @unlink($this->storage.'/app/private/logos/a.png');
        });

        $this->artisan('app:backup')->assertFailed();

        $this->assertSame([$good], glob($this->storage.'/backups/*'));
        $this->assertNull(BackupService::missingLogo($good));
    }

    public function test_a_persistent_copy_failure_fails_and_cleans_up(): void
    {
        $this->bindCopyHook(fn () => throw new \RuntimeException('disk full'));

        $this->artisan('app:backup')->assertFailed();

        $this->assertSame([], glob($this->storage.'/backups/*'));
    }
}
