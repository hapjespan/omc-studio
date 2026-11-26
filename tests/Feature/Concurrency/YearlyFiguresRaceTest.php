<?php

namespace Tests\Feature\Concurrency;

use App\Models\FinalisedYear;
use App\Models\YearlyFigure;
use Tests\Support\TwoProcessRunner;

class YearlyFiguresRaceTest extends RaceTestCase
{
    private const FINALISE_REFUSED = 'Nog 1 kostenpost in onbekende allocatie: '.self::UNALLOCATED;

    private const FIGURES_REFUSED = '2026 is definitief in canvas '.self::CANVAS.'. Heropen het jaar eerst';

    private const RACES = 3;

    private string $claim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->claim = sys_get_temp_dir().'/finalise-figures-claim-'.getmypid();
    }

    protected function tearDown(): void
    {
        $this->forgetClaim();

        parent::tearDown();
    }

    public function test_saving_yearly_figures_against_finalising_ends_with_one_success_and_one_refusal(): void
    {
        for ($race = 0; $race < self::RACES; $race++) {
            $this->forgetClaim();
            $this->reopen();

            $outcomes = (new TwoProcessRunner)->run(
                base_path('tests/Support/finalise-figures-race-worker.php'),
                [(string) $this->canvas->id, (string) $this->year->id, (string) $this->unallocated->id, $this->claim],
                $this->environment,
            );

            sort($outcomes);

            $finalised = FinalisedYear::on('race')->where('canvas_id', $this->canvas->id)->exists();
            $saved = YearlyFigure::on('race')->where('cost_record_id', $this->unallocated->id)->exists();

            $this->assertSame($finalised ? [self::FIGURES_REFUSED, 'ok'] : [self::FINALISE_REFUSED, 'ok'], $outcomes);
            $this->assertNotSame($finalised, $saved);
        }
    }

    private function reopen(): void
    {
        FinalisedYear::on('race')->where('canvas_id', $this->canvas->id)->delete();
        YearlyFigure::on('race')->where('cost_record_id', $this->unallocated->id)->delete();
    }

    private function forgetClaim(): void
    {
        if (file_exists($this->claim)) {
            unlink($this->claim);
        }
    }
}
