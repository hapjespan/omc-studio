<?php

namespace Database\Factories;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company().' '.Str::random(6)];
    }
}
