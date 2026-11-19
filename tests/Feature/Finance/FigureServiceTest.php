<?php

namespace Tests\Feature\Finance;

use App\Calculation\Figures;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\SystemNotice;
use App\Models\YearlyFigure;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Finance\FigureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FigureServiceTest extends TestCase
{
    use RefreshDatabase;

    private Canvas $canvas;

    protected function setUp(): void
    {
        parent::setUp();
        Area::firstOrCreate(['key' => 'supporting_activities'], ['kind' => 'activities', 'position' => 8, 'carries_costs' => true]);
        $organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        UnknownAllocationDomain::ensure($this->canvas);
        $record = CostRecord::factory()->create(['organisation_id' => $organisation->id]);
        YearlyFigure::factory()->create([
            'cost_record_id' => $record->id,
            'fiscal_year_id' => FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026])->id,
            'budget_cents' => 1234,
        ]);
    }

    public function test_a_closing_calculation_returns_the_figures_and_records_nothing(): void
    {
        $figures = app(FigureService::class)->figures($this->canvas, 2026);

        $this->assertInstanceOf(Figures::class, $figures);
        $this->assertSame(1234, $figures->canvasTotal['budget']);
        $this->assertSame(0, SystemNotice::count());
    }

    private function breakClosing(): void
    {
        $record = CostRecord::firstOrFail();
        $archived = Element::factory()->create(['canvas_id' => $this->canvas->id, 'archived_at' => now()]);
        $set = AllocationSet::factory()->create([
            'canvas_id' => $this->canvas->id,
            'cost_record_id' => $record->id,
            'fiscal_year_id' => FiscalYear::firstOrFail()->id,
        ]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $archived->id]);
    }

    public function test_a_failed_check_returns_no_figures_and_records_one_notice_with_occurrences(): void
    {
        $this->breakClosing();
        $service = app(FigureService::class);

        $this->assertNull($service->figures($this->canvas, 2026, ['costKind' => 'other', 'ownerId' => null]));
        $this->assertNull($service->figures($this->canvas, 2026, ['costKind' => 'other']));

        $notice = SystemNotice::sole();
        $this->assertSame('closing_check_failed', $notice->kind);
        $this->assertSame($this->canvas->id, $notice->canvas_id);
        $this->assertSame(2, $notice->occurrences);
        $this->assertSame(['year' => 2026, 'filter' => ['costKind' => 'other'], 'check' => 'budget', 'differenceCents' => -1234], array_intersect_key($notice->details, array_flip(['year', 'filter', 'check', 'differenceCents'])));
    }

    public function test_a_different_filter_gets_its_own_notice(): void
    {
        $this->breakClosing();
        $service = app(FigureService::class);

        $service->figures($this->canvas, 2026);
        $service->figures($this->canvas, 2026, ['costKind' => 'other']);

        $this->assertSame(2, SystemNotice::count());
    }

    public function test_the_unfiltered_call_is_what_finalise_and_snapshot_use(): void
    {
        $this->assertSame(1234, app(FigureService::class)->figures($this->canvas, 2026)?->canvasTotal['budget']);
    }
}
