<?php

namespace Database\Factories;

use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\YearlyFigure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YearlyFigure>
 */
class YearlyFigureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cost_record_id' => CostRecord::factory(),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->create([
                'organisation_id' => CostRecord::findOrFail($attributes['cost_record_id'])->organisation_id,
            ])->id,
            'budget_cents' => fake()->numberBetween(0, 100_000_000),
        ];
    }
}
