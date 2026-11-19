<?php

namespace Tests\Feature\Canvas;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Models\Statement;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViewDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_view_carries_the_areas_the_active_elements_with_their_fields_and_the_statements(): void
    {
        (new ReferenceDataStep)();
        $canvas = Canvas::factory()->create();
        $other = Canvas::factory()->create();
        $domain = Element::factory()->create(['canvas_id' => $canvas->id, 'name' => 'Onderwijs', 'description' => 'Alles over lessen', 'rationale' => 'Kern', 'modality_id' => Modality::where('key', 'adaptive')->value('id')]);
        Element::factory()->create(['canvas_id' => $canvas->id, 'name' => 'Oud', 'archived_at' => now()]);
        Element::factory()->create(['canvas_id' => $other->id, 'name' => 'Elders']);
        Statement::create(['canvas_id' => $canvas->id, 'kind' => 'mission', 'body' => 'Leren', 'position' => 1]);

        $this->actingAs(User::factory()->create())->get("/canvases/{$canvas->id}/view")->assertInertia(fn (Assert $page) => $page
            ->has('layout.areas', 8)
            ->where('layout.directionLabels.mission', 'Missie')
            ->has('elements', 1)
            ->where('elements.0.id', $domain->id)
            ->where('elements.0.area', 'primary_activities')
            ->where('elements.0.modality', 'A')
            ->where('elements.0.description', 'Alles over lessen')
            ->where('elements.0.rationale', 'Kern')
            ->has('statements', 1)
            ->where('statements.0.body', 'Leren')
            ->where('modalities.1.colour', '#1E5FBF'));
    }
}
