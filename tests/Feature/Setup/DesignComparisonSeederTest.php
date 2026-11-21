<?php

namespace Tests\Feature\Setup;

use App\Models\AllocationSet;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\YearlyFigure;
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
}
