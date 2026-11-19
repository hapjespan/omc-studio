<?php

namespace Tests\Feature\Exchange;

use App\Models\CostRecord;
use App\Models\User;
use App\Services\Exchange\CanvasImportValidator;
use App\Services\Exchange\FullImportValidator;
use App\Services\Snapshot\SnapshotContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FullImportLargeFileTest extends CanvasExchangeTestCase
{
    private const BULK = 21_000;

    /** @var list<int> */
    private array $levels = [];

    public function test_a_full_file_of_almost_50_megabytes_imports_within_60_seconds_with_validation_outside_the_transaction(): void
    {
        ini_set('memory_limit', '1G');
        set_time_limit(0);

        $content = $this->file();
        $baseline = DB::transactionLevel();

        $this->app->bind(FullImportValidator::class, fn ($app) => new class($app->make(CanvasImportValidator::class), $app->make(SnapshotContent::class), function () {
            $this->levels[] = DB::transactionLevel();
        }) extends FullImportValidator
        {

            public function __construct(CanvasImportValidator $validator, SnapshotContent $content, private readonly \Closure $probe)
            {
                parent::__construct($validator, $content);
            }

            public function check(string $content): array
            {
                ($this->probe)();

                return parent::check($content);
            }
        });

        $started = microtime(true);

        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', $content)], ['Accept' => 'application/json'])->assertOk();

        $spent = microtime(true) - $started;

        fwrite(STDERR, sprintf(
            "\nP-4 %d cost records, %d canvases, %d bytes: commit %.2f s, peak %d MB on %s, %s cores, PHP %s\n",
            self::BULK + 2, 3, strlen($content), $spent, memory_get_peak_usage(true) >> 20, php_uname('s').' '.php_uname('r'), trim((string) shell_exec('nproc')), PHP_VERSION,
        ));

        $this->assertLessThan(60, $spent);
        $this->assertSame([$baseline], $this->levels);
        $this->assertSame(self::BULK + 2, CostRecord::count());
        $this->assertSame(3, DB::table('canvases')->whereNotNull('organisation_id')->count());
    }

    private function file(): string
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());

        $data = json_decode((string) $this->get('/admin/export/full')->assertOk()->getContent(), true);

        $this->wipe();

        $text = str_repeat('x', 1900);
        $records = $data['organisations'][0]['cost_records'];

        for ($index = 0; $index < self::BULK; $index++) {
            $records[] = [
                'id' => (string) Str::uuid(),
                'code' => 'BULK-'.$index,
                'name' => 'Bulk '.$index,
                'cost_kind' => 'other',
                'supplier' => 'Acme',
                'owner' => 'Jansen',
                'cost_centre' => 'K1',
                'application' => 'ERP',
                'description' => $text,
                'archived_at' => null,
                'yearly_figures' => [['year' => 2026, 'budget_cents' => 1000, 'forecast_cents' => null, 'run_bp' => null, 'change_bp' => null, 'fte_hundredths' => null, 'reference_date' => null, 'remark' => null]],
            ];
        }

        $data['organisations'][0]['cost_records'] = $records;
        $data['organisations'][0]['canvases'][] = [...$data['organisations'][0]['canvases'][0], 'name' => 'Tweede canvas'];
        $data['organisations'][0]['canvases'][] = [...$data['organisations'][0]['canvases'][0], 'name' => 'Derde canvas'];

        $content = (string) json_encode($data);
        unset($data, $records);

        $this->assertGreaterThan(40_000_000, strlen($content));
        $this->assertLessThanOrEqual(CanvasImportValidator::MAX_BYTES, strlen($content));

        return $content;
    }
}
