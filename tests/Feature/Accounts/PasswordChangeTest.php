<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'a-long-enough-secret';

    private const NEXT = 'another-long-secret';

    private const THIS_DEVICE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const SECOND_DEVICE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const OTHER_USER = 'cccccccccccccccccccccccccccccccccccccccc';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_account_screen_opens_for_a_signed_in_user(): void
    {
        $this->actingAs($this->user())->get('/account')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Account/Show'));
    }

    public function test_a_guest_is_sent_to_the_sign_in_screen(): void
    {
        $this->get('/account')->assertRedirect('/sign-in');
        $this->put('/account/password')->assertRedirect('/sign-in');
    }

    public function test_the_password_is_changed_with_the_current_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/account/password', $this->fields())
            ->assertRedirect('/account')
            ->assertSessionHas('status', 'Wachtwoord gewijzigd');

        $this->assertTrue(Hash::check(self::NEXT, (string) $user->fresh()?->password));
    }

    public function test_a_wrong_current_password_is_refused_and_changes_nothing(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/account/password', $this->fields(['current_password' => 'wrong-but-long-enough']))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::CURRENT, (string) $user->fresh()?->password));
    }

    public function test_an_empty_current_password_is_refused(): void
    {
        $this->actingAs($this->user())->put('/account/password', $this->fields(['current_password' => '']))
            ->assertSessionHasErrors(['current_password' => __('account.current_password_required')]);
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $this->actingAs($this->user())->put('/account/password', $this->fields([
            'password' => self::CURRENT,
            'password_confirmation' => self::CURRENT,
        ]))->assertSessionHasErrors('password');
    }

    public function test_the_new_password_must_be_repeated_and_long_enough(): void
    {
        $this->actingAs($this->user())->put('/account/password', $this->fields(['password_confirmation' => 'mistyped-secret']))
            ->assertSessionHasErrors('password');

        $this->actingAs($this->user())->put('/account/password', $this->fields(['password' => 'kort', 'password_confirmation' => 'kort']))
            ->assertSessionHasErrors('password');
    }

    public function test_a_second_session_of_the_user_ends_and_the_current_one_stays(): void
    {
        $user = $this->onThisDevice();
        $this->storedSession(self::SECOND_DEVICE, $user);

        $this->put('/account/password', $this->fields())->assertRedirect('/account');

        $this->assertDatabaseMissing('sessions', ['id' => self::SECOND_DEVICE]);
        $this->assertDatabaseMissing('sessions', ['id' => self::THIS_DEVICE]);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_session_of_another_user_stays(): void
    {
        $this->onThisDevice();
        $this->storedSession(self::OTHER_USER, $this->user('reader@example.test'));

        $this->put('/account/password', $this->fields());

        $this->assertDatabaseHas('sessions', ['id' => self::OTHER_USER]);
    }

    public function test_the_change_is_written_to_the_security_log_with_the_count_and_without_the_address(): void
    {
        $log = storage_path('logs/security-'.now()->format('Y-m-d').'.log');
        File::delete($log);
        $user = $this->onThisDevice('writer@example.test');
        $this->storedSession(self::SECOND_DEVICE, $user);

        $this->put('/account/password', $this->fields());

        $written = (string) File::get($log);
        $this->assertStringContainsString('password_changed', $written);
        $this->assertStringContainsString('"sessions_ended":1', $written);
        $this->assertStringNotContainsString('writer@example.test', $written);
    }

    public function test_five_wrong_attempts_lead_to_the_waiting_message(): void
    {
        $user = $this->user();
        $wrong = $this->fields(['current_password' => 'wrong-but-long-enough']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)->put('/account/password', $wrong);
        }

        $this->actingAs($user)->put('/account/password', $this->fields())
            ->assertSessionHasErrors(['current_password' => 'Te veel pogingen. Probeer het over 60 seconden opnieuw']);

        $this->assertTrue(Hash::check(self::CURRENT, (string) $user->fresh()?->password));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function fields(array $overrides = []): array
    {
        return [...['current_password' => self::CURRENT, 'password' => self::NEXT, 'password_confirmation' => self::NEXT], ...$overrides];
    }

    private function user(?string $email = null): User
    {
        return User::factory()->create(['password' => self::CURRENT] + array_filter(['email' => $email]));
    }

    private function onThisDevice(?string $email = null): User
    {
        config(['session.driver' => 'database']);
        $user = $this->user($email);
        $this->storedSession(self::THIS_DEVICE, $user);
        $this->withCookie((string) config('session.cookie'), self::THIS_DEVICE);

        return $user;
    }

    private function storedSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'payload' => base64_encode((string) json_encode([
                Auth::guard('web')->getName() => $user->id,
                'signed_in_at' => now()->getTimestamp(),
            ])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
