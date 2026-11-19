<?php

namespace Database\Factories;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Canvas>
 */
class CanvasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'kind' => CanvasKind::Canvas,
            'name' => fake()->unique()->words(3, true),
        ];
    }

    public function template(): static
    {
        return $this->state(['organisation_id' => null, 'kind' => CanvasKind::Template]);
    }
}
