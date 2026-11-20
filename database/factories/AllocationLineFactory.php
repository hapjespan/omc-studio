<?php

namespace Database\Factories;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Element;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocationLine>
 */
class AllocationLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'allocation_set_id' => AllocationSet::factory(),
            'element_id' => fn (array $attributes) => Element::factory()->create([
                'canvas_id' => AllocationSet::findOrFail($attributes['allocation_set_id'])->canvas_id,
            ])->id,
            'basis_points' => 10000,
            'position' => 0,
        ];
    }
}
