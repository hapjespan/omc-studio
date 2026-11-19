<?php

namespace Tests\Feature\Shell;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BackRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private string $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organisation = Organisation::factory()->create();
        $this->page = "/organisations/{$this->organisation->id}/cost-records";
        $this->actingAs(User::factory()->administrator()->create());
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function inertia(array $extra = []): array
    {
        return ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion(), 'X-Requested-With' => 'XMLHttpRequest'] + $extra;
    }

    /** @return TestResponse<Response> */
    private function addYear(mixed $year): TestResponse
    {
        return $this->post("/organisations/{$this->organisation->id}/fiscal-years", ['year' => $year], ['X-Inertia' => 'true']);
    }

    public function test_back_returns_to_the_page_last_visited_through_a_client_side_navigation(): void
    {
        $this->get('/account');
        $this->get($this->page, $this->inertia());

        $this->addYear(2026)->assertRedirect($this->page);
    }

    public function test_a_failed_validation_goes_back_to_the_page_last_visited_through_a_client_side_navigation(): void
    {
        $this->get('/account');
        $this->get($this->page, $this->inertia());

        $this->addYear(1)->assertRedirect($this->page)->assertSessionHasErrors();
    }

    public function test_an_inertia_visit_that_answers_with_a_redirect_does_not_overwrite_the_previous_url(): void
    {
        $canvas = Canvas::factory()->create();
        $this->get('/account');
        $this->get($this->page, $this->inertia());
        $this->get("/canvases/{$canvas->id}", $this->inertia())->assertRedirect();

        $this->addYear(2026)->assertRedirect($this->page);
    }

    public function test_a_partial_reload_does_not_overwrite_the_previous_url(): void
    {
        $this->get('/account');
        $this->get($this->page, $this->inertia());
        $this->get("{$this->page}?search=x", $this->inertia(['X-Inertia-Partial-Component' => 'Finance/CostRecords', 'X-Inertia-Partial-Data' => 'rows']))->assertOk();

        $this->addYear(2026)->assertRedirect($this->page);
    }
}
