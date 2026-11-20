<?php

namespace Tests\Feature\Layout;

use App\Models\Layout;
use App\Models\User;
use Tests\Feature\Structure\StructureTestCase;

class DirectionRefusalTest extends StructureTestCase
{
    public function test_a_layout_for_the_direction_band_is_refused_and_nothing_is_stored(): void
    {
        $administrator = User::factory()->administrator()->create();
        $domain = $this->domain();
        $this->canvas->refresh();

        $response = $this->actingAs($administrator)->putJson("/canvases/{$this->canvas->id}/layout", [
            'structure_version' => (int) $this->canvas->structure_version,
            'layout_version' => (int) $this->canvas->layout_version,
            'format_version' => 1,
            'areas' => ['direction' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 180, 'z' => 0]],
            'elements' => [(string) $domain->id => ['mode' => 'automatic']],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('rule');
        $this->assertSame(0, Layout::query()->where('canvas_id', $this->canvas->id)->count());
        $this->assertSame(0, (int) $this->canvas->fresh()->layout_version);
    }
}
