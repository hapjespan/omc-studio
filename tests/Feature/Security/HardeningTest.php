<?php

namespace Tests\Feature\Security;

use App\Support\QueryTrace;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    public function test_the_undo_route_sends_a_guest_to_sign_in(): void
    {
        $this->post('/undo/some-token')->assertRedirect('/sign-in');
    }
}
