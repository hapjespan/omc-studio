<?php

namespace Tests\Feature\Accounts;

use App\Models\UndoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignInUndoPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sign_in_removes_the_expired_undo_records_of_any_user(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-1']);
        $other = User::factory()->create();
        UndoRecord::create(['token' => 'old', 'user_id' => $other->id, 'kind' => 'element_deletion', 'payload' => [], 'expires_at' => now()->subMinute()]);
        UndoRecord::create(['token' => 'live', 'user_id' => $other->id, 'kind' => 'element_deletion', 'payload' => [], 'expires_at' => now()->addMinutes(5)]);

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'Correct-Horse-1']);

        $this->assertSame(['live'], UndoRecord::pluck('token')->all());
    }
}
