<?php

namespace Tests\Feature\Concurrency;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\User;
use Tests\Support\TwoProcessRunner;

class ClearSetFinaliseRaceTest extends RaceTestCase
{
    private const ALLOCATED = 'Licentie';

    private const FINALISE_REFUSED = 'Nog 1 kostenpost in onbekende allocatie: '.self::ALLOCATED;

    private const CLEAR_REFUSED = '2026 is definitief. Heropen het jaar eerst';

    private CostRecord $allocated;

    private User $user;

    private string $element;

    private string $claim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onRace(function (): void {
            $this->allocated = CostRecord::where('name', self::ALLOCATED)->firstOrFail();
            $this->user = User::factory()->administrator()->create();
            $this->element = (string) AllocationLine::firstOrFail()->element_id;
        });

        $this->claim = sys_get_temp_dir().'/clear-set-finalise-claim-'.getmypid();

        $this->forgetClaim();
    }

    protected function tearDown(): void
    {
        $this->forgetClaim();

        parent::tearDown();
    }

    public function test_clearing_a_set_against_finalising_ends_with_one_success_and_one_refusal(): void
    {
        foreach (['finalise', 'clear'] as $first) {
            $this->forgetClaim();
            $this->restore();

            $outcomes = (new TwoProcessRunner)->run(
                base_path('tests/Support/clear-set-race-worker.php'),
                [(string) $this->canvas->id, (string) $this->year->id, (string) $this->allocated->id, (string) $this->user->id, $this->claim, $first],
                $this->environment,
            );

            sort($outcomes);

            $finalised = FinalisedYear::on('race')->where('canvas_id', $this->canvas->id)->exists();
            $allocated = AllocationSet::on('race')->where('cost_record_id', $this->allocated->id)->exists();

            $this->assertSame($finalised ? [self::CLEAR_REFUSED, 'ok'] : [self::FINALISE_REFUSED, 'ok'], $outcomes);
            $this->assertSame($finalised, $allocated);

            if ($first === 'clear') {
                $this->assertFalse($finalised);
            }
        }
    }

    private function restore(): void
    {
        $this->onRace(function (): void {
            FinalisedYear::where('canvas_id', $this->canvas->id)->delete();

            if (AllocationSet::where('cost_record_id', $this->allocated->id)->exists()) {
                return;
            }

            $set = AllocationSet::create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $this->allocated->id, 'fiscal_year_id' => $this->year->id]);
            AllocationLine::create(['allocation_set_id' => $set->id, 'element_id' => $this->element, 'basis_points' => 10_000, 'position' => 0]);
        });
    }

    private function forgetClaim(): void
    {
        if (file_exists($this->claim)) {
            unlink($this->claim);
        }
    }
}
