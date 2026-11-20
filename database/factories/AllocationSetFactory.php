<?php

namespace Database\Factories;

use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocationSet>
 */
class AllocationSetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'canvas_id' => Canvas::factory(),
            'cost_record_id' => CostRecord::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
        ];
    }
}
