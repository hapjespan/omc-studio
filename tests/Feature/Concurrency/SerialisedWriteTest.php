<?php

namespace Tests\Feature\Concurrency;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Support\SerialisedWrite;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\TwoProcessRunner;
use Tests\TestCase;

class SerialisedWriteTest extends TestCase
{
    use RefreshDatabase;

    private ?string $file = null;

    protected function tearDown(): void
    {
        if (config('database.connections.race') !== null) {
            DB::connection('race')->table('audit_entries')->delete();
            DB::connection('race')->table('organisations')->delete();
            DB::purge('race');
        }

        foreach (['', '-wal', '-shm'] as $suffix) {
            if ($this->file !== null && file_exists($this->file.$suffix)) {
                unlink($this->file.$suffix);
            }
        }

        parent::tearDown();
    }

    public function test_two_guarded_writes_on_one_aggregate_end_with_one_success_and_one_refusal(): void
    {
        $environment = $this->prepareRaceDatabase();

        $organisation = Organisation::on('race')->create(['name' => 'Noord']);

        $outcomes = (new TwoProcessRunner)->run(base_path('tests/Support/serialised-write-worker.php'), [(string) $organisation->id], $environment);

        sort($outcomes);

        $this->assertSame(['ok', 'refused'], $outcomes);
        $this->assertNotNull(Organisation::on('race')->findOrFail($organisation->id)->archived_at);
    }

    public function test_the_shipped_sqlite_connection_runs_wal_and_immediate_with_a_fifteen_second_busy_timeout(): void
    {
        $this->assertSame(
            ['wal', 15000, 'IMMEDIATE'],
            [
                config('database.connections.sqlite.journal_mode'),
                config('database.connections.sqlite.busy_timeout'),
                config('database.connections.sqlite.transaction_mode'),
            ],
        );
    }

    public function test_aggregates_are_locked_in_the_order_organisation_canvas_user(): void
    {
        $organisation = Organisation::factory()->create();
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $locks = [];

        DB::listen(function (QueryExecuted $query) use (&$locks): void {
            if (str_starts_with($query->sql, 'select')) {
                $locks[] = [$this->tableOf($query->sql), $query->bindings[0]];
            }
        });

        app(SerialisedWrite::class)->run([$second, $canvas, $first, $organisation], fn () => null);

        $this->assertSame([
            ['organisations', $organisation->id],
            ['canvases', $canvas->id],
            ['users', $first->id],
            ['users', $second->id],
        ], $locks);
    }

    public function test_an_aggregate_outside_the_lock_order_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SerialisedWrite::class)->run([Element::factory()->create()], fn () => null);
    }

    /** @return array<string, string> */
    private function prepareRaceDatabase(): array
    {
        $connection = (string) config('database.default');

        if (config('database.connections.'.$connection.'.driver') !== 'sqlite') {
            config(['database.connections.race' => config('database.connections.'.$connection)]);

            return ['DB_CONNECTION' => $connection];
        }

        $this->file = base_path('.scratch/concurrency/race-'.getmypid().'.sqlite');

        if (! is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0755, true);
        }

        touch($this->file);

        config(['database.connections.race' => ['database' => $this->file] + (array) config('database.connections.sqlite')]);

        Artisan::call('migrate', ['--database' => 'race', '--force' => true]);

        return ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->file];
    }

    private function tableOf(string $sql): string
    {
        preg_match('/from "([a-z_]+)"/', $sql, $matches);

        return $matches[1];
    }
}
