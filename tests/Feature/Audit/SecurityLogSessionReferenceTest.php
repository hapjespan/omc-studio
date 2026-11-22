<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Services\Audit\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityLogSessionReferenceTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/security-ref-'.getmypid();
        File::ensureDirectoryExists($this->directory);
        config(['logging.channels.security.path' => $this->directory.'/security.log']);
        Log::forgetChannel('security');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        Log::forgetChannel('security');

        parent::tearDown();
    }

    public function test_two_sessions_of_one_user_get_different_references(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $first = $this->recordIn($this->store(), 'one');
        $second = $this->recordIn($this->store(), 'two');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $first);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_one_session_keeps_its_reference_across_the_sign_in_regeneration(): void
    {
        $store = $this->store();
        $before = $this->recordIn($store, 'sign_in_failed');
        $store->regenerate();
        $after = $this->recordIn($store, 'sign_in_succeeded');
        $again = $this->recordIn($store, 'sign_out');

        $this->assertSame($before, $after);
        $this->assertSame($before, $again);
    }

    public function test_the_reference_is_stored_in_no_table_other_than_the_session_and_console_entries_carry_none(): void
    {
        $reference = $this->recordIn($this->store(), 'probe');
        $this->app->instance('request', Request::create('/'));
        SecurityLog::record('console_probe');

        $lines = array_values(array_filter(explode("\n", (string) File::get($this->logFile()))));
        $this->assertStringContainsString('"session_ref":null', end($lines));

        foreach (Schema::getTableListing() as $table) {
            if (str_ends_with($table, 'sessions')) {
                continue;
            }
            $this->assertStringNotContainsString($reference, (string) json_encode(DB::table($table)->get()));
        }
    }

    private function store(): Store
    {
        return new Store('test', new ArraySessionHandler(120));
    }

    private function recordIn(Store $store, string $event): string
    {
        $request = Request::create('/');
        $request->setLaravelSession($store);
        $this->app->instance('request', $request);
        SecurityLog::record($event);

        $lines = array_values(array_filter(explode("\n", (string) File::get($this->logFile()))));
        preg_match('/"session_ref":"([^"]+)"/', end($lines), $match);

        return $match[1] ?? '';
    }

    private function logFile(): string
    {
        return $this->directory.'/security-'.now()->format('Y-m-d').'.log';
    }
}
