<?php

namespace Tests\Feature\Console;

use App\Services\Operations\InstallSteps;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = base_path('.scratch/install-'.Str::random(8));
        mkdir($this->storage.'/logs', 0755, true);
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        Process::run('rm -rf '.escapeshellarg($this->storage));

        parent::tearDown();
    }

    private function keyPath(): string
    {
        return $this->storage.'/app.key';
    }

    public function test_the_first_run_generates_a_key_file_a_second_run_keeps_it(): void
    {
        $this->artisan('app:install')->assertSuccessful();

        $key = (string) file_get_contents($this->keyPath());
        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]+={0,2}$/', $key);
        $this->assertSame(32, strlen((string) base64_decode(substr($key, 7), true)));
        $this->assertSame('0600', substr(sprintf('%o', (int) fileperms($this->keyPath())), -4));

        $this->artisan('app:install')->assertSuccessful();
        $secondOutput = Artisan::output();

        $this->assertSame($key, file_get_contents($this->keyPath()));
        $this->assertStringNotContainsString('base64:', $secondOutput);
        $this->assertStringNotContainsString(substr($key, 7), $secondOutput);
    }

    public function test_an_empty_key_file_from_an_interrupted_start_is_replaced(): void
    {
        touch($this->keyPath());

        $this->artisan('app:install')->assertSuccessful();

        $this->assertStringStartsWith('base64:', (string) file_get_contents($this->keyPath()));
    }

    public function test_the_stored_key_file_is_the_authority_for_the_run(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        file_put_contents($this->keyPath(), $key);

        $this->artisan('app:install')->assertSuccessful();

        $this->assertSame($key, config('app.key'));
        $this->assertSame($key, file_get_contents($this->keyPath()));
    }

    public function test_the_registered_steps_run_in_order_on_every_run(): void
    {
        $calls = [];
        $steps = $this->app->make(InstallSteps::class);
        $steps->add(function () use (&$calls) {
            $calls[] = 'first';
        });
        $steps->add(function () use (&$calls) {
            $calls[] = 'second';
        });

        $this->artisan('app:install')->assertSuccessful();
        $this->artisan('app:install')->assertSuccessful();

        $this->assertSame(['first', 'second', 'first', 'second'], $calls);
    }

    public function test_the_migrations_run_once_and_stay_unchanged_on_a_second_run(): void
    {
        $this->artisan('app:install')->assertSuccessful();

        $applied = DB::table('migrations')->pluck('migration')->all();
        $this->assertNotEmpty($applied);

        $this->artisan('app:install')->assertSuccessful();

        $this->assertSame($applied, DB::table('migrations')->pluck('migration')->all());
    }

    public function test_the_entrypoint_exports_the_key_and_hands_storage_over_after_the_install(): void
    {
        $script = (string) file_get_contents(base_path('docker/entrypoint.sh'));
        $this->assertMatchesRegularExpression('/^set -e$/m', $script);

        $root = $this->storage.'/container';
        mkdir($root.'/bin', 0755, true);
        mkdir($root.'/bootstrap/cache', 0755, true);
        copy(base_path('docker/entrypoint.sh'), $root.'/entrypoint.sh');
        file_put_contents($root.'/bin/php', "#!/bin/sh\nprintf 'base64:stub-key' > storage/app.key\n");
        file_put_contents($root.'/bin/chown', "#!/bin/sh\necho \"\$*\" >> chown.log\n");
        file_put_contents($root.'/bin/apache2-foreground', "#!/bin/sh\necho \"key=\$APP_KEY chown=\$(cat chown.log)\"\n");
        chmod($root.'/bin/php', 0755);
        chmod($root.'/bin/chown', 0755);
        chmod($root.'/bin/apache2-foreground', 0755);

        $result = Process::path($root)->env(['PATH' => $root.'/bin:'.getenv('PATH')])->run('sh entrypoint.sh');

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertStringContainsString('key=base64:stub-key chown=-R www-data:www-data storage bootstrap/cache', $result->output());
    }
}
