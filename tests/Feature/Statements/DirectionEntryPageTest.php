<?php

namespace Tests\Feature\Statements;

use App\Models\Canvas;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectionEntryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_entry_page_carries_the_statements(): void
    {
        (new ReferenceDataStep)();
        $canvas = Canvas::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        $this->put("/canvases/{$canvas->id}/statements/mission", ['body' => 'M']);
        $this->post("/canvases/{$canvas->id}/goals", ['title' => 'A']);

        $this->get("/canvases/{$canvas->id}/entry")->assertInertia(fn ($page) => $page->where('statements.mission', 'M')->where('statements.vision', null)->has('statements.goals', 1));
    }
}
