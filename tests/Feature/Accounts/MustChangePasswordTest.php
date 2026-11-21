<?php

namespace Tests\Feature\Accounts;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_TIME = 'Km7pQr4Ts9Vx2Bnd';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function pending(): User
    {
        return User::factory()->administrator()->create([
            'password' => self::ONE_TIME,
            'must_change_password' => true,
            'one_time_password_expires_at' => Carbon::now()->addHours(24),
        ]);
    }

    public function test_every_other_route_leads_to_the_new_password_screen(): void
    {
        Route::middleware('web')->get('/probe', fn () => response('ok'));

        $this->actingAs($this->pending());

        $this->get('/')->assertRedirect('/password/new');
        $this->get('/probe')->assertRedirect('/password/new');
        $this->post('/undo/'.str_repeat('a', 26))->assertRedirect('/password/new');
    }

    public function test_the_screen_itself_and_signing_out_stay_reachable(): void
    {
        Route::middleware('web')->post('/sign-out', fn () => response('ok'));

        $this->actingAs($this->pending());

        $this->get('/password/new')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/NewPassword'));
        $this->post('/sign-out')->assertOk();
    }

    public function test_a_user_without_the_flag_is_not_sent_anywhere(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
    }

    public function test_a_guest_is_sent_to_the_sign_in_screen(): void
    {
        $this->get('/password/new')->assertRedirect('/sign-in');
    }

    public function test_an_expired_one_time_password_ends_the_session(): void
    {
        $user = $this->pending();
        $user->forceFill(['one_time_password_expires_at' => Carbon::now()->subSecond()])->save();

        $this->actingAs($user)->get('/password/new')
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors(['email' => 'Je eenmalige wachtwoord is verlopen. Vraag een nieuw wachtwoord aan']);

        $this->assertGuest();
    }

    public function test_a_password_under_twelve_characters_is_refused(): void
    {
        $this->actingAs($this->pending())->from('/password/new')
            ->put('/password/new', ['password' => 'elf-tekens1', 'password_confirmation' => 'elf-tekens1'])
            ->assertRedirect('/password/new')
            ->assertSessionHasErrors(['password' => 'Wachtwoord: gebruik minstens 12 tekens']);

        $this->assertTrue(User::query()->sole()->must_change_password);
    }

    public function test_a_common_password_is_refused(): void
    {
        $this->actingAs($this->pending())
            ->put('/password/new', ['password' => 'unbelievable', 'password_confirmation' => 'unbelievable'])
            ->assertSessionHasErrors(['password' => 'Wachtwoord: dit wachtwoord is te bekend, kies een ander']);
    }

    public function test_a_confirmation_that_differs_is_refused(): void
    {
        $this->actingAs($this->pending())
            ->put('/password/new', ['password' => 'een-lang-genoeg-geheim', 'password_confirmation' => 'iets-anders-maar-lang'])
            ->assertSessionHasErrors(['password' => 'Herhaal wachtwoord: de twee wachtwoorden zijn niet gelijk']);
    }

    public function test_the_one_time_password_may_not_be_kept(): void
    {
        $this->actingAs($this->pending())
            ->put('/password/new', ['password' => self::ONE_TIME, 'password_confirmation' => self::ONE_TIME])
            ->assertSessionHasErrors(['password' => 'Wachtwoord: kies een ander wachtwoord dan het eenmalige wachtwoord']);

        $this->assertTrue(User::query()->sole()->must_change_password);
    }

    public function test_a_new_password_opens_the_application_and_clears_the_history(): void
    {
        $user = $this->pending();

        $response = $this->actingAs($user)->put('/password/new', [
            'password' => 'een-lang-genoeg-geheim',
            'password_confirmation' => 'een-lang-genoeg-geheim',
        ]);

        $response->assertRedirect('/')->assertSessionHas('inertia.clear_history', true);
        $user->refresh();

        $this->assertTrue(Hash::check('een-lang-genoeg-geheim', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->one_time_password_expires_at);
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk();
    }

    public function test_a_second_session_of_the_user_ends_when_the_new_password_is_saved(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->pending();
        $this->storedSession('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $user);
        $this->storedSession('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $user);
        $this->withCookie((string) config('session.cookie'), 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->put('/password/new', [
            'password' => 'een-lang-genoeg-geheim',
            'password_confirmation' => 'een-lang-genoeg-geheim',
        ])->assertRedirect('/');

        $this->assertDatabaseMissing('sessions', ['id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertAuthenticatedAs($user);
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

    public function test_the_change_is_audited_without_password_material(): void
    {
        $user = $this->pending();

        $this->actingAs($user)->put('/password/new', [
            'password' => 'een-lang-genoeg-geheim',
            'password_confirmation' => 'een-lang-genoeg-geheim',
        ]);

        $entry = AuditEntry::query()->where('action', 'password_changed')->sole();

        $this->assertArrayNotHasKey('password', $entry->changes);
        $this->assertStringNotContainsString('geheim', (string) AuditEntry::query()->get()->toJson());
        $this->assertStringNotContainsString(self::ONE_TIME, (string) AuditEntry::query()->get()->toJson());
    }
}
