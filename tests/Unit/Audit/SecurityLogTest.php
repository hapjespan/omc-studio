<?php

namespace Tests\Unit\Audit;

use App\Services\Audit\SecurityLog;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
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
