<?php

namespace Tests\Feature\Setup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_no_user(): void
    {
        $this->seed();

        $this->assertSame(0, User::count());
    }

    public function test_the_seeder_carries_no_comments(): void
    {
        $this->assertDoesNotMatchRegularExpression('#/\*|//|^\s*\*#m', (string) file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }
}
