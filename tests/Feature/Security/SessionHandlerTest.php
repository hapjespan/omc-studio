<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Session\DatabaseSessionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sessions_table_holds_no_address_and_no_user_agent(): void
    {
        $columns = Schema::getColumnListing('sessions');
        sort($columns);

        $this->assertSame(['id', 'last_activity', 'payload', 'user_id'], $columns);
    }

    public function test_a_stored_session_row_holds_the_user_and_nothing_about_the_request(): void
    {
        $this->useDatabaseSessions(0, 100);

        $this->actingAs((new User)->forceFill(['id' => 7]))->get('/session-probe')->assertOk();

        $this->assertInstanceOf(DatabaseSessionHandler::class, $this->app['session']->driver()->getHandler());

        $row = (array) DB::table('sessions')->sole();
        ksort($row);

        $this->assertSame(['id', 'last_activity', 'payload', 'user_id'], array_keys($row));
        $this->assertSame(7, (int) $row['user_id']);
        $this->assertNotEmpty($row['payload']);
    }

    public function test_the_lottery_removes_an_expired_row_and_keeps_a_live_one(): void
    {
        $this->useDatabaseSessions(1, 1);

        $lifetime = (int) config('session.lifetime');
        $this->storeRow('expired-session', now()->subMinutes($lifetime + 1)->getTimestamp());
        $this->storeRow('live-session', now()->subMinutes($lifetime - 1)->getTimestamp());

        $this->get('/session-probe')->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'expired-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'live-session']);
    }

    private function useDatabaseSessions(int $chance, int $outOf): void
    {
        config(['session.driver' => 'database', 'session.lottery' => [$chance, $outOf]]);

        Route::middleware('web')->get('/session-probe', fn () => response('ok'));
    }

    private function storeRow(string $id, int $lastActivity): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => null,
            'payload' => base64_encode(serialize([])),
            'last_activity' => $lastActivity,
        ]);
    }
}
