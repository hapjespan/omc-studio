<?php

namespace Database\Factories;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Element>
 */
class ElementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'canvas_id' => Canvas::factory(),
            'area_id' => fn () => Area::firstOrCreate(
                ['key' => 'primary_activities'],
                ['kind' => 'activities', 'position' => 7, 'carries_costs' => true],
            )->id,
            'kind' => ElementKind::Domain,
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
