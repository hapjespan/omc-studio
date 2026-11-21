<?php

namespace Tests\Support;

use App\Models\User;

trait ActsAsAdministrator
{
    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }
}
