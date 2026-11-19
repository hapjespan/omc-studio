<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\User;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FrozenClock;
use Tests\Support\RaceDatabase;
use Tests\Support\TwoProcessRunner;
use Tests\TestCase;

class TotpReplayTest extends TestCase
{
    use RefreshDatabase;

    private const SECONDS = 30;

    private const SECRET = 'ADUMJO5634NPDEKW';

    private const FROZEN = '2026-11-19 10:00:00';

    private RaceDatabase $race;

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = new RaceDatabase;
        FrozenClock::freeze(self::FROZEN);
    }

    protected function tearDown(): void
    {
        $this->race->clean(['audit_entries', 'users']);

        parent::tearDown();
    }

    public function test_a_code_of_the_current_step_is_accepted_and_the_step_is_remembered(): void
    {
        $user = $this->user();

        $this->assertTrue($this->verifier()->verify($user, $this->code(0)));

        $this->assertSame($this->step(0), $user->fresh()?->two_factor_last_time_step);
    }

    public function test_one_step_either_way_is_accepted(): void
    {
        $this->assertTrue($this->verifier()->verify($this->user(), $this->code(-1)));
        $this->assertTrue($this->verifier()->verify($this->user(), $this->code(1)));
    }

    public function test_two_steps_away_are_refused(): void
    {
        $this->assertFalse($this->verifier()->verify($this->user(), $this->code(-2)));
        $this->assertFalse($this->verifier()->verify($this->user(), $this->code(2)));
    }

    public function test_the_step_edges_hold_when_the_clock_moves_on(): void
    {
        $code = $this->code(0);

        FrozenClock::advance((self::SECONDS - 1).' seconds');
        $this->assertTrue($this->verifier()->verify($this->user(), $code));

        FrozenClock::freeze(self::FROZEN);
        FrozenClock::advance(self::SECONDS.' seconds');
        $this->assertTrue($this->verifier()->verify($this->user(), $code));

        FrozenClock::advance(self::SECONDS.' seconds');
        $this->assertFalse($this->verifier()->verify($this->user(), $code));
    }

    public function test_the_same_code_is_refused_a_second_time(): void
    {
        $user = $this->user();
        $code = $this->code(0);
        $this->verifier()->verify($user, $code);

        try {
            $this->verifier()->verify($user, $code);
            $this->fail('The replayed code was accepted.');
        } catch (ValidationException $refusal) {
            $this->assertSame(['Code: deze code is al gebruikt, wacht op de volgende code'], $refusal->errors()['code']);
        }
    }

    public function test_an_earlier_step_is_refused_after_a_later_one_was_accepted(): void
    {
        $user = $this->user();
        $this->verifier()->verify($user, $this->code(0));

        $this->expectException(ValidationException::class);

        $this->verifier()->verify($user, $this->code(-1));
    }

    public function test_a_stale_user_instance_cannot_replay_an_accepted_code(): void
    {
        $user = $this->user();
        $sameUserInAnotherRequest = User::query()->findOrFail($user->getKey());
        $code = $this->code(0);

        $this->assertTrue($this->verifier()->verify($user, $code));

        $this->expectException(ValidationException::class);

        $this->verifier()->verify($sameUserInAnotherRequest, $code);
    }

    public function test_two_simultaneous_submissions_of_one_code_leave_one_winner(): void
    {
        FrozenClock::release();
        $environment = $this->race->prepare();
        $step = intdiv(time(), self::SECONDS);
        $user = User::on('race')->create([
            'name' => 'Race',
            'email' => 'race@example.test',
            'password' => 'a-long-enough-secret',
            'role' => Role::Reader,
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
        ]);

        $outcomes = (new TwoProcessRunner)->run(
            base_path('tests/Support/totp-replay-worker.php'),
            [(string) $user->getKey(), (new Google2FA)->oathTotp(self::SECRET, $step)],
            $environment,
        );

        sort($outcomes);

        $this->assertSame(['ok', 'refused'], $outcomes);
        $this->assertGreaterThanOrEqual($step, (int) User::on('race')->findOrFail($user->getKey())->two_factor_last_time_step);
    }

    public function test_a_user_without_a_secret_is_refused(): void
    {
        $this->assertFalse($this->verifier()->verify(User::factory()->create(), $this->code(0)));
    }

    public function test_input_that_is_not_a_code_is_refused(): void
    {
        $this->assertFalse($this->verifier()->verify($this->user(), 'abcdef'));
        $this->assertFalse($this->verifier()->verify($this->user(), ''));
    }

    private function verifier(): TotpVerifier
    {
        return app(TotpVerifier::class);
    }

    private function user(): User
    {
        return User::factory()->create([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => Carbon::now(),
        ]);
    }

    private function step(int $offset): int
    {
        return intdiv(Carbon::now()->getTimestamp(), self::SECONDS) + $offset;
    }

    private function code(int $offset): string
    {
        return (new Google2FA)->oathTotp(self::SECRET, $this->step($offset));
    }
}
