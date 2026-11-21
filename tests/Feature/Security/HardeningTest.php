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
}
