<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\Throttle;
use App\Services\Notices\LockoutCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function expectLockouts(int $times): void
    {
        $this->mock(LockoutCounter::class, fn (MockInterface $counter) => $counter->shouldReceive('record')->times($times));
    }

    public function test_four_wrong_attempts_then_a_correct_fifth_count_no_lockout(): void
    {
        $this->expectLockouts(0);
        $throttle = new Throttle('probe-a', true);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->assertTrue($throttle->claim('subject'));
            $throttle->fail('subject');
        }

        $this->assertTrue($throttle->claim('subject'));
        $throttle->clear('subject');

        $this->assertTrue($throttle->claim('subject'));
        $this->assertFalse($throttle->tooManyAttempts('subject'));
    }

    public function test_five_wrong_attempts_count_exactly_one_lockout(): void
    {
        $this->expectLockouts(1);
        $throttle = new Throttle('probe-b', true);

        for ($attempt = 0; $attempt < Throttle::LIMIT; $attempt++) {
            $this->assertTrue($throttle->claim('subject'));
            $throttle->fail('subject');
        }

        $this->assertFalse($throttle->claim('subject'));
        $this->assertTrue($throttle->tooManyAttempts('subject'));
    }

    public function test_a_refunded_attempt_does_not_count_even_when_it_reached_the_limit(): void
    {
        $this->expectLockouts(0);
        $throttle = new Throttle('probe-c', true);

        for ($attempt = 0; $attempt < Throttle::LIMIT; $attempt++) {
            $this->assertTrue($throttle->claim('subject'));
            $attempt < Throttle::LIMIT - 1 ? $throttle->fail('subject') : $throttle->refund('subject');
        }

        $this->assertFalse($throttle->tooManyAttempts('subject'));
    }

    public function test_a_reused_new_password_neither_consumes_an_attempt_nor_clears_the_count(): void
    {
        $user = User::factory()->create(['password' => 'correct horse battery']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($user)->put('/account/password', ['current_password' => 'wrong wrong wrong', 'password' => 'another long phrase 1', 'password_confirmation' => 'another long phrase 1']);
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)->put('/account/password', ['current_password' => 'correct horse battery', 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])
                ->assertSessionHasErrors(['password' => 'Wachtwoord: kies een ander wachtwoord dan je huidige wachtwoord']);
        }

        $this->actingAs($user)->put('/account/password', ['current_password' => 'wrong wrong wrong', 'password' => 'another long phrase 1', 'password_confirmation' => 'another long phrase 1']);
        $this->actingAs($user)->put('/account/password', ['current_password' => 'wrong wrong wrong', 'password' => 'another long phrase 1', 'password_confirmation' => 'another long phrase 1'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/account/password', ['current_password' => 'correct horse battery', 'password' => 'brand new long phrase', 'password_confirmation' => 'brand new long phrase'])
            ->assertSessionHasErrors(['current_password' => 'Te veel pogingen. Probeer het over 60 seconden opnieuw']);
    }
}
