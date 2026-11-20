<?php

namespace Tests\Feature\Concurrency;

use App\Models\FinalisedYear;
use Tests\Support\TwoProcessRunner;

class FinaliseRaceTest extends RaceTestCase
{
    public function test_two_finalise_calls_on_one_canvas_and_year_end_with_one_success_and_one_refusal(): void
    {
        $outcomes = (new TwoProcessRunner)->run(
            base_path('tests/Support/finalise-race-worker.php'),
            [(string) $this->canvas->id, (string) $this->year->id],
            $this->environment,
        );

        sort($outcomes);

        $this->assertSame(['2026 is al definitief', 'ok'], $outcomes);
        $this->assertSame(1, FinalisedYear::on('race')->where('canvas_id', $this->canvas->id)->count());
    }
}
