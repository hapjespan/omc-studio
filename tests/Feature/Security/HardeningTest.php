<?php

namespace Tests\Feature\Security;

use App\Support\QueryTrace;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    public function test_session_payloads_are_encrypted_by_default_and_at_rest(): void
    {
        $this->assertMatchesRegularExpression("/'encrypt' => env\('SESSION_ENCRYPT', true\)/", $this->file('config/session.php'));
        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true$/m', $this->file('env.example'));

        config(['session.encrypt' => true, 'session.driver' => 'database']);
        app('session')->forgetDrivers();
        $store = app('session')->driver();
        $store->put('one_time_password', 'Plaintext-secret-1234');
        $store->save();

        $payload = (string) DB::table('sessions')->where('id', $store->getId())->value('payload');

        $this->assertNotSame('', $payload);
        $this->assertStringNotContainsString('Plaintext-secret-1234', $payload);
        $this->assertStringNotContainsString('Plaintext-secret-1234', (string) base64_decode($payload, true));
    }

    public function test_the_docker_ignore_is_exactly_the_allowlist_the_dockerfile_needs(): void
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $this->file('.dockerignore'))), fn (string $line) => $line !== '' && ! str_starts_with($line, '#')));

        $this->assertSame([
            '*',
            '!app', '!artisan', '!bootstrap', '!composer.json', '!composer.lock', '!config', '!database', '!docker', '!lang',
            '!package.json', '!package-lock.json', '!public', '!resources', '!routes', '!storage', '!vite.config.js',
            'bootstrap/cache', 'database/*.sqlite*', 'public/build', 'storage/*',
            '.worktrees', '.scratch', '.remember', '.playwright-browsers', 'test-results', '.forbidden-names', '.env', '.env.*',
        ], $lines);
    }

    public function test_the_image_creates_every_storage_folder_the_ignore_file_leaves_out(): void
    {
        $dockerfile = $this->file('Dockerfile');
        $entrypoint = $this->file('docker/entrypoint.sh');

        foreach (['storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $folder) {
            $this->assertStringContainsString($folder, $dockerfile, $folder);
        }

        foreach (['storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app'] as $folder) {
            $this->assertStringContainsString($folder, $entrypoint, $folder);
        }
    }

    public function test_the_image_ships_the_production_php_ini_and_hides_php(): void
    {
        $dockerfile = $this->file('Dockerfile');

        $this->assertStringContainsString('php.ini-production', $dockerfile);
        $this->assertStringContainsString('expose_php=Off', $dockerfile);
        $this->assertMatchesRegularExpression('/a2enmod[^\n&]*\bheaders\b/', $dockerfile);
    }

    public function test_apache_sends_nosniff_for_static_files_only(): void
    {
        $apache = $this->file('docker/apache.conf');

        $this->assertMatchesRegularExpression('/<FilesMatch "[^"]*\\\.\(js\|[^"]*\)\$">\s*Header always set X-Content-Type-Options "nosniff"\s*<\/FilesMatch>/', $apache);
        $this->assertSame(1, substr_count($apache, 'X-Content-Type-Options'));
        $this->assertDoesNotMatchRegularExpression('/<FilesMatch[^>]*php/i', $apache);
    }

    public function test_the_production_ini_keeps_the_container_environment_visible_to_php(): void
    {
        $this->assertStringContainsString('variables_order=EGPCS', $this->file('Dockerfile'));
    }

    public function test_a_mapped_query_exception_keeps_the_sql_without_bindings_and_names_the_first_app_frame(): void
    {
        $exception = new QueryException('sqlite', 'select * from users where email = ?', ['secret@example.nl'], new Exception('failed'));
        $frames = [
            ['file' => base_path('vendor/laravel/framework/src/Illuminate/Database/Connection.php'), 'line' => 10],
            ['file' => base_path('app/Services/Accounts/SignInService.php'), 'line' => 42],
            ['file' => base_path('app/Http/Controllers/SignInController.php'), 'line' => 7],
        ];
        (new ReflectionProperty(Exception::class, 'trace'))->setValue($exception, $frames);

        $message = QueryTrace::message($exception);

        $this->assertStringContainsString('select * from users where email = ?', $message);
        $this->assertStringContainsString('app/Services/Accounts/SignInService.php:42', $message);
        $this->assertStringNotContainsString('secret@example.nl', $message);
    }

    public function test_the_undo_route_sends_a_guest_to_sign_in(): void
    {
        $this->post('/undo/some-token')->assertRedirect('/sign-in');
    }

    public function test_the_constraints_record_the_cookie_prefix_exemption_and_the_digest_gap(): void
    {
        $constraints = $this->file('docs/constraints.md');

        $this->assertStringContainsString('`__Host-` cookie prefix', $constraints);
        $this->assertStringContainsString('not pinned by digest', $constraints);
    }
}
