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

    public function test_the_image_ships_the_production_php_ini_and_hides_php(): void
    {
        $dockerfile = $this->file('Dockerfile');

        $this->assertStringContainsString('php.ini-production', $dockerfile);
        $this->assertStringContainsString('expose_php=Off', $dockerfile);
        $this->assertMatchesRegularExpression('/a2enmod[^\n&]*\bheaders\b/', $dockerfile);
    }

    public function test_apache_sends_nosniff_for_static_files(): void
    {
        $this->assertMatchesRegularExpression('/^\s*Header always set X-Content-Type-Options "nosniff"\s*$/m', $this->file('docker/apache.conf'));
    }
}
