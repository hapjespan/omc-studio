<?php

namespace Tests\Feature\Shell;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Tests\TestCase;

class BackRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_back_returns_to_the_page_last_visited_through_a_client_side_navigation(): void
    {
        $organisation = Organisation::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        $page = "/organisations/{$organisation->id}/cost-records";

        $this->get('/account');
        $this->get($page, ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion(), 'X-Requested-With' => 'XMLHttpRequest']);
        $response = $this->post("/organisations/{$organisation->id}/fiscal-years", ['year' => 2026], ['X-Inertia' => 'true']);

        $response->assertRedirect($page);
    }
}
