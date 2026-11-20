<?php

namespace Database\Factories;

use App\Enums\CostKind;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostRecord>
 */
class CostRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->unique()->words(3, true),
            'cost_kind' => CostKind::Other,
            'owner_id' => fn (array $attributes) => Owner::factory()->create(['organisation_id' => $attributes['organisation_id']])->id,
        ];
    }
}
