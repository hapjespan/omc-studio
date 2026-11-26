<?php

namespace Tests\Feature\Allocation;

use App\Models\Canvas;

class RedistributionPreviewTest extends AllocationTestCase
{
    public function test_preview_lists_affected_records_and_percentages_per_year_for_the_subtree(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $second = $this->activity($domain, 'A2');
        $other = $this->target('Elders');
        $record = $this->costRecord('Licentie', 2025);
        $this->fiscalYear(2026);
        $this->allocate($record, 2025, [$first->id => 3000, $second->id => 2000, $other->id => 5000]);
        $this->allocate($record, 2026, [$first->id => 10000]);
        $this->allocate($this->costRecord('Hosting', 2026), 2026, [$second->id => 4000, $other->id => 6000]);

        $response = $this->actingAs($this->admin())->getJson("/canvases/{$this->canvas->id}/elements/{$domain->id}/redistribution")->assertOk();

        $years = array_column($response->json('years'), null, 'year');
        $this->assertSame([1, 5000], [$years[2025]['records'], $years[2025]['basis_points']]);
        $this->assertSame([2, 14000], [$years[2026]['records'], $years[2026]['basis_points']]);
        $this->assertSame('Licentie', $years[2025]['affected'][0]['name']);
        $this->assertSame(5000, $years[2025]['affected'][0]['basis_points']);
    }

    public function test_preview_is_empty_without_lines(): void
    {
        $element = $this->target();

        $this->actingAs($this->admin())->getJson("/canvases/{$this->canvas->id}/elements/{$element->id}/redistribution")
            ->assertOk()->assertJsonPath('years', []);
    }

    public function test_a_year_finalised_in_this_canvas_refuses_the_preview(): void
    {
        $element = $this->target();
        $this->allocate($this->costRecord(), 2026, [$element->id => 10000]);
        $this->finalise(2026);

        $this->actingAs($this->admin())->getJson("/canvases/{$this->canvas->id}/elements/{$element->id}/redistribution")
            ->assertUnprocessable()->assertJsonValidationErrors(['rule'])
            ->assertJsonPath('errors.rule.0', '2026 is definitief. Heropen het jaar eerst');
    }

    public function test_an_element_of_another_canvas_is_not_found(): void
    {
        $element = $this->target();

        $this->actingAs($this->admin())->getJson('/canvases/'.Canvas::factory()->create()->id."/elements/{$element->id}/redistribution")->assertNotFound();
    }
}
