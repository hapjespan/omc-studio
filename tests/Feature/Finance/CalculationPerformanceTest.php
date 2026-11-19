<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\FigureService;
use Database\Factories\PerformanceDatasetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_figures_for_500_elements_and_1000_cost_records_come_within_one_second(): void
    {
        $dataset = (new PerformanceDatasetFactory)->create();
        $service = app(FigureService::class);

        $started = hrtime(true);
        $figures = $service->figures($dataset['canvas'], $dataset['year']);
        $seconds = (hrtime(true) - $started) / 1e9;

        $this->assertNotNull($figures);
        $this->assertCount(500, $figures->elements);
        $this->assertCount(1000, $figures->lines);
        $this->assertLessThan(1.0, $seconds, sprintf('%.3f s on %s, %d cores, PHP %s (K-6: result depends on the machine)', $seconds, php_uname('s').' '.php_uname('m'), (int) shell_exec('nproc'), PHP_VERSION));
    }
}
