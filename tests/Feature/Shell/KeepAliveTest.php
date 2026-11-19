<?php

namespace Tests\Feature\Shell;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeepAliveTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->post('/session/keep-alive')->assertRedirect('/sign-in');
    }

    public function test_a_signed_in_user_gets_no_content_and_stays_signed_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/session/keep-alive')->assertNoContent();

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_does_not_extend_the_absolute_limit(): void
    {
        $signedInAt = time() - 600;

        $this->actingAs(User::factory()->create())
            ->withSession(['signed_in_at' => $signedInAt])
            ->post('/session/keep-alive');

        $this->assertSame($signedInAt, session('signed_in_at'));
    }
}
