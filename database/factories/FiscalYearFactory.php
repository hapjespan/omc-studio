<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'year' => fake()->unique()->numberBetween(2000, 2100),
        ];
    }
}
