<?php

namespace Tests\Feature\Performance;

use App\Models\FiscalYear;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Snapshot\Snapshots;
use Database\Factories\PerformanceDatasetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ExchangeVolumeTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLES = 20;

    private const BUDGET_SECONDS = 1.0;

    public function test_the_delivery_3_screens_and_exports_of_500_elements_and_1000_cost_records_come_within_one_second_at_the_95th_percentile(): void
    {
        set_time_limit(0);
        $dataset = (new PerformanceDatasetFactory)->create();
        $canvas = $dataset['canvas'];
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $fiscalYear = FiscalYear::where('organisation_id', $canvas->organisation_id)->where('year', $dataset['year'])->firstOrFail();
        $snapshot = app(Snapshots::class)->take($canvas, $fiscalYear, 'Meting');
        $this->assertInstanceOf(Snapshot::class, $snapshot);

        $routes = [
            'Versies' => "/canvases/{$canvas->id}/versions",
            'Snapshot view' => "/snapshots/{$snapshot->id}/view",
            'Canvas JSON export' => "/canvases/{$canvas->id}/export/json",
            'Cost records CSV export' => "/organisations/{$canvas->organisation_id}/export/cost-records.csv",
            'Allocations CSV export' => "/canvases/{$canvas->id}/export/allocations.csv",
            'Full export' => '/admin/export/full',
        ];

        $report = [];
        foreach ($routes as $label => $path) {
            $this->fetch($path);
            $samples = [];
            for ($run = 0; $run < self::SAMPLES; $run++) {
                $started = hrtime(true);
                $this->fetch($path);
                $samples[] = (hrtime(true) - $started) / 1e9;
            }
            sort($samples);
            $p95 = $samples[(int) ceil(0.95 * self::SAMPLES) - 1];
            $report[$label] = $p95;
            $this->assertLessThan(self::BUDGET_SECONDS, $p95, sprintf('%s: 95th percentile %.3f s over %d samples', $label, $p95, self::SAMPLES));
        }

        fwrite(STDERR, sprintf(
            "\nP-1 D3, 500 Elements and 1000 Cost records, 95th percentile of %d samples on %s %s, %s cores, PHP %s: %s\n",
            self::SAMPLES, php_uname('s'), php_uname('m'), trim((string) shell_exec('nproc')), PHP_VERSION,
            implode(', ', array_map(fn (string $label, float $p95): string => sprintf('%s %.0f ms', $label, $p95 * 1000), array_keys($report), $report)),
        ));
    }

    private function fetch(string $path): void
    {
        $response = $this->get($path)->assertOk();
        $response->baseResponse instanceof StreamedResponse ? $response->streamedContent() : $response->getContent();
    }
}
