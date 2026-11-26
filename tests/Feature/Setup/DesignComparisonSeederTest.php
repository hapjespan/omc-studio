<?php

namespace Tests\Feature\Setup;

use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\YearlyFigure;
use App\Services\Finance\FigureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DesignComparisonSeeder;
use Tests\TestCase;

class DesignComparisonSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seed_reproduces_the_figures_of_the_design_boards(): void
    {
        $this->seed(DesignComparisonSeeder::class);

        $this->assertSame(10, CostRecord::count());
        $this->assertSame(74400000, (int) YearlyFigure::sum('budget_cents'));
        $this->assertSame(8, AllocationSet::count());
        $this->assertSame(['KP-009', 'KP-010'], CostRecord::whereNotIn('id', AllocationSet::pluck('cost_record_id'))->orderBy('code')->pluck('code')->all());
        $this->assertSame(1, Element::where('name', 'Werkplek & beheer')->where('kind', 'cluster')->count());
    }

    public function test_the_seed_gives_the_domains_the_figures_the_screenshots_show(): void
    {
        $this->seed(DesignComparisonSeeder::class);

        $canvas = Canvas::where('name', 'Voorbeeldcanvas')->firstOrFail();
        $figures = app(FigureService::class)->figures($canvas, 2026);
        $budget = fn (string $name): int => $figures->elements[Element::where('canvas_id', $canvas->id)->where('name', $name)->value('id')]['total']['budget'];

        $this->assertFalse((bool) $canvas->organisation->is_sample);
        $this->assertSame(7950000, $budget('Opvang & onderwijs'));
        $this->assertSame(10100000, $budget('Ouders & communicatie'));
        $this->assertSame(2940000, $budget('Financiën'));
        $this->assertSame(0, Element::where('parent_id', Element::where('canvas_id', $canvas->id)->where('name', 'Strategie')->value('id'))->count());
    }
}
