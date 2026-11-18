<?php

namespace Tests\Unit\Audit;

use App\Models\User;
use App\Services\Audit\SecurityLog;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;
use Tests\TestCase;

class SecurityLogTest extends TestCase
{
    /** @var list<MessageLogged> */
    private array $logged = [];

    private function capture(): void
    {
        Log::listen(function (MessageLogged $event): void {
            $this->logged[] = $event;
        });
    }

    private function serialised(): string
    {
        return (string) json_encode(array_map(fn (MessageLogged $e) => [$e->message, $e->context], $this->logged));
    }

    public function test_a_recorded_event_carries_no_email_address_even_when_given_one(): void
    {
        $this->capture();

        SecurityLog::record('sign_in_failed', ['user_id' => 7, 'email' => 'jan@example.nl', 'note' => 'tried jan@example.nl', 'ip' => '10.0.0.1']);

        $line = $this->serialised();
        $this->assertStringContainsString('sign_in_failed', $line);
        $this->assertStringContainsString('"user_id":7', $line);
        $this->assertStringNotContainsString('@', $line);
        $this->assertStringNotContainsString('10.0.0.1', $line);
    }

    public function test_the_security_channel_writes_json_lines_and_survives_invalid_utf8(): void
    {
        $this->assertSame(JsonFormatter::class, config('logging.channels.security.formatter'));

        $this->capture();
        SecurityLog::record('sign_in_failed', ['note' => "bad \xB1 byte"]);

        $this->assertNotEmpty($this->logged);
    }

    public function test_the_signed_in_user_is_recorded_without_being_passed(): void
    {
        $this->capture();
        $this->actingAs(new User(['id' => 5]));

        SecurityLog::record('sign_out');

        $this->assertStringContainsString('"user_id":', $this->serialised());
    }

    public function test_an_exception_context_carries_the_route_name(): void
    {
        $this->capture();
        Route::get('/probe-error', fn () => throw new \RuntimeException('boom'))->name('probe.error');

        $this->get('/probe-error');
        $this->assertStringContainsString('probe.error', $this->serialised());
    }

    public function test_the_security_channel_rotates_at_ninety_days_and_the_error_log_at_fourteen(): void
    {
        $this->assertSame('daily', config('logging.channels.security.driver'));
        $this->assertSame(90, config('logging.channels.security.days'));
        $this->assertSame(14, config('logging.channels.daily.days'));
        $this->assertStringContainsString('LOG_STACK=daily', (string) file_get_contents(base_path('env.example')));
    }

    public function test_a_database_exception_is_reported_with_its_sql_and_never_its_bindings(): void
    {
        $this->capture();

        report(new QueryException('sqlite', 'select * from users where email = ?', ['secret@example.nl'], new \Exception('failed')));

        $this->assertNotEmpty($this->logged);
        $this->assertStringNotContainsString('secret@example.nl', $this->serialised());
    }

    public function test_the_apache_error_log_format_leaves_out_the_client_address(): void
    {
        $apache = (string) file_get_contents(base_path('docker/apache.conf'));

        $this->assertMatchesRegularExpression('/^\s*ErrorLogFormat\s+"[^"]*"\s*$/m', $apache);
        $this->assertDoesNotMatchRegularExpression('/ErrorLogFormat.*%(a|h|\{c\}a)\b/', $apache);
    }
}
