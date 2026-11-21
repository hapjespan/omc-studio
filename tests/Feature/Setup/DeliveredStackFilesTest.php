<?php

namespace Tests\Feature\Setup;

use Tests\TestCase;

class DeliveredStackFilesTest extends TestCase
{
    private function read(string $relativePath): string
    {
        $path = base_path($relativePath);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_compose_publishes_the_application_on_loopback_only(): void
    {
        $compose = $this->read('docker-compose.yml');

        $this->assertStringContainsString('"127.0.0.1:8080:80"', $compose);
        $this->assertDoesNotMatchRegularExpression('/-\s*"?(?!127\.0\.0\.1)[\d.]*:?\d+:80"?/', $compose);
    }

    public function test_compose_keeps_storage_on_one_named_volume_and_debug_off(): void
    {
        $compose = $this->read('docker-compose.yml');

        $this->assertStringContainsString('storage:/var/www/html/storage', $compose);
        $this->assertMatchesRegularExpression('/^volumes:\n\s+storage:/m', $compose);
        $this->assertStringContainsString('APP_DEBUG: "false"', $compose);
    }

    public function test_the_image_has_two_stages_and_the_required_extensions(): void
    {
        $dockerfile = $this->read('Dockerfile');

        $this->assertSame(2, preg_match_all('/^FROM /m', $dockerfile));
        $this->assertStringContainsString('FROM node:24', $dockerfile);
        $this->assertStringContainsString('FROM php:8.4-apache', $dockerfile);
        $this->assertStringContainsString('pdo_sqlite', $dockerfile);
        $this->assertMatchesRegularExpression('/docker-php-ext-install[^\n]*\bgd\b/', $dockerfile);
    }

    public function test_apache_writes_no_access_log_and_the_entrypoint_installs_then_serves(): void
    {
        $this->assertStringContainsString('CustomLog /dev/null', $this->read('docker/apache.conf'));

        $entrypoint = $this->read('docker/entrypoint.sh');
        $this->assertLessThan(strpos($entrypoint, 'apache2-foreground'), strpos($entrypoint, 'php artisan app:install'));
    }

    public function test_the_env_template_is_secret_free_and_debug_off(): void
    {
        $template = $this->read('env.example');

        $this->assertStringContainsString('APP_DEBUG=false', $template);
        $this->assertDoesNotMatchRegularExpression('/^(DB_PASSWORD|REDIS_PASSWORD|AWS_SECRET_ACCESS_KEY|MAIL_PASSWORD)=.+/m', $template);
    }

    public function test_the_makefile_has_up_and_down(): void
    {
        $makefile = $this->read('Makefile');

        $this->assertMatchesRegularExpression('/^up:/m', $makefile);
        $this->assertMatchesRegularExpression('/^down:/m', $makefile);
    }

    public function test_the_image_keeps_generated_state_and_argument_values_out(): void
    {
        $this->assertStringContainsString('storage/app.key', $this->read('.dockerignore'));
        $this->assertStringContainsString('zend.exception_ignore_args=On', $this->read('Dockerfile'));
    }

    public function test_the_image_accepts_uploads_above_the_php_defaults(): void
    {
        $dockerfile = $this->read('Dockerfile');

        $this->assertStringContainsString('upload_max_filesize=51M', $dockerfile);
        $this->assertStringContainsString('post_max_size=56M', $dockerfile);
    }

    public function test_the_writable_directories_exist_before_the_autoload_scripts_run(): void
    {
        $dockerfile = $this->read('Dockerfile');

        $this->assertLessThan(
            strpos($dockerfile, 'composer dump-autoload'),
            strpos($dockerfile, 'mkdir -p storage/framework/cache'),
        );
    }

    public function test_the_docker_context_excludes_local_and_private_state(): void
    {
        $entries = array_map('trim', explode("\n", $this->read('.dockerignore')));

        foreach (['.forbidden-names', '.scratch', '.worktrees', '.playwright-browsers', '.remember', '.env'] as $entry) {
            $this->assertContains($entry, $entries);
        }
    }

    public function test_the_dockerignore_carries_no_comments(): void
    {
        $this->assertDoesNotMatchRegularExpression('/^\s*#/m', $this->read('.dockerignore'));
    }
}
