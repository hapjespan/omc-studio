<?php

namespace Database\Factories;

use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinalisedYear>
 */
class FinalisedYearFactory extends Factory
{
    public function definition(): array
    {
        return [
            'canvas_id' => Canvas::factory(),
            'fiscal_year_id' => FiscalYear::factory(),
            'finalised_at' => now(),
        ];
    }
}
