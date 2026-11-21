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
}
