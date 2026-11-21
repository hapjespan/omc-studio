<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\PasswordService;
use App\Services\Accounts\Throttle;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PasswordServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['password' => 'Old-password-1234', 'must_change_password' => true]);
        session()->start();
    }

    private function service(): PasswordService
    {
        return app(PasswordService::class);
    }

    private function store(): Session
    {
        return app('session.store');
    }

    private function refusal(callable $call): ValidationException
    {
        try {
            $call();
        } catch (ValidationException $refusal) {
            return $refusal;
        }

        $this->fail('Expected a refusal.');
    }

    public function test_change_sets_the_new_password_and_regenerates_the_session(): void
    {
        $before = $this->store()->getId();

        $this->service()->change($this->user, 'Old-password-1234', 'Brand-new-password-5678', $this->store());

        $this->assertTrue(Hash::check('Brand-new-password-5678', (string) $this->user->fresh()->password));
        $this->assertNotSame($before, $this->store()->getId());
    }

    public function test_change_refuses_a_wrong_current_password_and_counts_the_attempt(): void
    {
        for ($i = 0; $i < Throttle::LIMIT; $i++) {
            $this->assertArrayHasKey('current_password', $this->refusal(fn () => $this->service()->change($this->user, 'wrong', 'Brand-new-password-5678', $this->store()))->errors());
        }

        $this->assertTrue((new Throttle(PasswordService::CHANGE))->tooManyAttempts((string) $this->user->getKey()));
        $this->assertTrue(Hash::check('Old-password-1234', (string) $this->user->fresh()->password));
    }

    public function test_change_refuses_the_same_password_without_costing_an_attempt(): void
    {
        $this->assertArrayHasKey('password', $this->refusal(fn () => $this->service()->change($this->user, 'Old-password-1234', 'Old-password-1234', $this->store()))->errors());
        $this->assertFalse((new Throttle(PasswordService::CHANGE))->tooManyAttempts((string) $this->user->getKey()));
    }

    public function test_set_initial_clears_the_must_change_flag_and_refuses_reuse(): void
    {
        $this->assertArrayHasKey('password', $this->refusal(fn () => $this->service()->setInitial($this->user, 'Old-password-1234', $this->store()))->errors());

        $this->service()->setInitial($this->user, 'Brand-new-password-5678', $this->store());

        $fresh = $this->user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertNull($fresh->one_time_password_expires_at);
    }

    public function test_confirm_stamps_the_session_and_refuses_a_wrong_password(): void
    {
        $this->assertArrayHasKey('password', $this->refusal(fn () => $this->service()->confirm($this->user, 'wrong', null, $this->store()))->errors());
        $this->assertNull($this->store()->get('signed_in_at'));

        $before = $this->store()->getId();
        $this->service()->confirm($this->user, 'Old-password-1234', null, $this->store());

        $this->assertNotNull($this->store()->get('signed_in_at'));
        $this->assertNotSame($before, $this->store()->getId());
    }
}
