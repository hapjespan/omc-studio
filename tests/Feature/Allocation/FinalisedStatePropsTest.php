<?php

namespace Tests\Feature\Allocation;

use App\Models\Canvas;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

class FinalisedStatePropsTest extends AllocationTestCase
{
    public function test_the_cost_records_page_lists_canvases_with_who_finalised_and_when(): void
    {
        $user = User::factory()->administrator()->create(['name' => 'Anna']);
        $this->fiscalYear(2026);
        Canvas::factory()->create(['organisation_id' => $this->canvas->organisation_id, 'name' => 'Tweede']);
        $finalised = $this->finalise(2026);
        $finalised->update(['finalised_by' => $user->id, 'finalised_at' => '2026-11-18 10:00:00']);

        $this->actingAs($user)->get("/organisations/{$this->canvas->organisation_id}/cost-records")->assertInertia(fn (Assert $page) => $page
            ->has('canvases', 2)
            ->where('finalised.0.canvas_id', $this->canvas->id)
            ->where('finalised.0.year', 2026)
            ->where('finalised.0.at', '18-11-2026')
            ->where('finalised.0.by', 'Anna')
            ->has('finalised', 1));
    }
}
