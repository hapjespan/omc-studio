<?php

namespace Database\Factories;

use App\Models\SystemNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemNotice>
 */
class SystemNoticeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kind' => 'closing_check_failed',
            'fingerprint' => fake()->unique()->sha1(),
            'details' => [],
            'first_occurred_at' => now(),
            'last_occurred_at' => now(),
        ];
    }
}
