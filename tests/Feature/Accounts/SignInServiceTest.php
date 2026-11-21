<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use App\Services\Accounts\SignInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class SignInServiceTest extends TestCase
{
    use RefreshDatabase;

    private const WRONG = 'E-mailadres of wachtwoord is onjuist';

    private const THROTTLED = 'Te veel pogingen. Probeer het over 60 seconden opnieuw';

    /** @var array<int, MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        FrozenClock::freeze('2026-01-01 09:00:00');
        Log::listen(function (MessageLogged $event): void {
            $this->logged[] = $event;
        });
    }

    protected function tearDown(): void
    {
        FrozenClock::release();

        parent::tearDown();
    }

    private function service(): SignInService
    {
        return $this->app->make(SignInService::class);
    }

    private function refusal(string $email, string $password): string
    {
        try {
            $this->service()->attempt($email, $password);
        } catch (ValidationException $exception) {
            return $exception->errors()['email'][0];
        }

        $this->fail('the attempt was not refused');
    }

    /** @return array<int, MessageLogged> */
    private function events(string $event): array
    {
        return array_values(array_filter($this->logged, fn (MessageLogged $logged) => $logged->message === $event));
    }

    public function test_a_wrong_password_and_an_unknown_address_give_the_same_message(): void
    {
        $user = User::factory()->create();

        $this->assertSame(self::WRONG, $this->refusal($user->email, 'wrong-password'));
        $this->assertSame(self::WRONG, $this->refusal('nobody@example.test', 'password'));
    }

    public function test_a_failed_attempt_is_logged_without_the_address(): void
    {
        $user = User::factory()->create();

        $this->refusal($user->email, 'wrong-password');

        $events = $this->events('sign_in_failed');
        $this->assertCount(1, $events);
        $this->assertStringNotContainsString($user->email, (string) json_encode($events[0]->context));
    }

    public function test_the_sixth_attempt_is_refused_even_with_the_right_password(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertSame(self::WRONG, $this->refusal($user->email, 'wrong-password'));
        }

        $this->assertSame(self::THROTTLED, $this->refusal($user->email, 'password'));
        $this->assertCount(1, $this->events('sign_in_throttled'));
        $this->assertStringNotContainsString($user->email, (string) json_encode($this->events('sign_in_throttled')[0]->context));
        $this->assertGuest();
    }

    public function test_the_refusal_ends_after_sixty_seconds(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->refusal($user->email, 'wrong-password');
        }

        FrozenClock::advance('59 seconds');
        $this->assertSame('Te veel pogingen. Probeer het over 1 seconden opnieuw', $this->refusal($user->email, 'password'));

        FrozenClock::advance('1 second');
        $this->assertTrue($this->service()->attempt($user->email, 'password')->is($user));
    }

    public function test_four_failures_then_a_sign_in_clears_the_count(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->refusal($user->email, 'wrong-password');
        }

        $this->service()->attempt($user->email, 'password');
        Auth::logout();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->assertSame(self::WRONG, $this->refusal($user->email, 'wrong-password'));
        }
    }

    public function test_a_sign_in_starts_a_new_session_records_the_time_and_is_logged(): void
    {
        $user = User::factory()->create();
        $before = session()->getId();

        $signedIn = $this->service()->attempt(' '.strtoupper($user->email).' ', 'password');

        $this->assertTrue($signedIn->is($user));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertSame(now()->getTimestamp(), session()->get('signed_in_at'));
        $this->assertCount(1, $this->events('sign_in_succeeded'));
    }

    public function test_an_expired_one_time_password_is_refused_with_the_same_message(): void
    {
        $user = User::factory()->create();
        $password = $this->app->make(OneTimePassword::class)->issueTo($user);

        FrozenClock::advance('25 hours');

        $this->assertSame(self::WRONG, $this->refusal($user->email, $password));
        $this->assertCount(1, $this->events('sign_in_failed'));
        $this->assertGuest();
    }

    public function test_a_refusal_for_a_known_address_carries_the_user_identifier(): void
    {
        $user = User::factory()->create();

        $this->refusal($user->email, 'wrong-password');

        $context = $this->events('sign_in_failed')[0]->context;
        $this->assertSame($user->getKey(), $context['user_id']);
        $this->assertSame('wrong_password', $context['reason']);
        $this->assertStringNotContainsString($user->email, (string) json_encode($context));
    }

    public function test_a_refusal_for_an_unknown_address_carries_no_identifier(): void
    {
        $this->refusal('nobody@example.test', 'wrong-password');

        $context = $this->events('sign_in_failed')[0]->context;
        $this->assertNull($context['user_id']);
        $this->assertStringNotContainsString('nobody@example.test', (string) json_encode($context));
    }

    public function test_a_throttled_refusal_carries_the_identifier_when_the_address_resolves(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->refusal($user->email, 'wrong-password');
        }

        $this->refusal($user->email, 'password');
        $throttled = $this->events('sign_in_throttled');
        $this->assertSame($user->getKey(), $throttled[0]->context['user_id']);
    }
}
