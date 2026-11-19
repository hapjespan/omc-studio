<?php

namespace Tests\Feature\Statements;

use App\Enums\StatementKind;
use App\Models\Canvas;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectionStatementsTest extends TestCase
{
    use RefreshDatabase;

    private Canvas $canvas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canvas = Canvas::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
    }

    private function goals(): array
    {
        return Statement::where('kind', 'goal')->orderBy('position')->pluck('title')->all();
    }

    private function goal(string $title): Statement
    {
        $this->post("/canvases/{$this->canvas->id}/goals", ['title' => $title, 'body' => 'Uitleg'])->assertRedirect();

        return Statement::where('title', $title)->firstOrFail();
    }

    public function test_a_mission_is_saved_replaced_and_cleared_keeping_at_most_one(): void
    {
        $this->put("/canvases/{$this->canvas->id}/statements/mission", ['body' => 'Eerste'])->assertRedirect();
        $this->put("/canvases/{$this->canvas->id}/statements/mission", ['body' => 'Tweede'])->assertRedirect();

        $this->assertSame(['Tweede'], Statement::where('kind', 'mission')->pluck('body')->all());

        $this->put("/canvases/{$this->canvas->id}/statements/mission", ['body' => '  '])->assertRedirect();

        $this->assertSame(0, Statement::where('kind', 'mission')->count());
    }

    public function test_the_vision_behaves_the_same(): void
    {
        $this->put("/canvases/{$this->canvas->id}/statements/vision", ['body' => 'Verder kijken']);

        $this->assertSame('Verder kijken', Statement::where('kind', StatementKind::Vision)->firstOrFail()->body);
    }

    public function test_a_mission_over_two_thousand_characters_is_refused_by_name(): void
    {
        $this->put("/canvases/{$this->canvas->id}/statements/mission", ['body' => str_repeat('a', 2001)])->assertSessionHasErrors(['body' => 'Missie: gebruik maximaal 2000 tekens']);
        $this->put("/canvases/{$this->canvas->id}/statements/mission", ['body' => str_repeat('a', 2000)])->assertSessionHasNoErrors();
    }

    public function test_goals_are_added_at_the_end_and_need_a_title_of_at_most_150_characters(): void
    {
        $this->goal('A');
        $this->goal('B');

        $this->assertSame(['A', 'B'], $this->goals());
        $this->post("/canvases/{$this->canvas->id}/goals", ['title' => ' ', 'body' => 'x'])->assertSessionHasErrors(['title' => 'Titel: vul een titel in']);
        $this->post("/canvases/{$this->canvas->id}/goals", ['title' => str_repeat('a', 151)])->assertSessionHasErrors('title');
    }

    public function test_a_goal_is_edited(): void
    {
        $goal = $this->goal('A');

        $this->patch("/canvases/{$this->canvas->id}/goals/{$goal->id}", ['title' => 'Nieuw', 'body' => 'Anders'])->assertRedirect();

        $this->assertSame(['Nieuw', 'Anders'], [$goal->fresh()->title, $goal->fresh()->body]);
    }

    public function test_goals_move_up_and_down_and_stop_at_the_ends(): void
    {
        $this->goal('A');
        $this->goal('B');
        $third = $this->goal('C');

        $this->post("/canvases/{$this->canvas->id}/goals/{$third->id}/reorder", ['direction' => 'up']);
        $this->assertSame(['A', 'C', 'B'], $this->goals());

        $this->post("/canvases/{$this->canvas->id}/goals/{$third->id}/reorder", ['direction' => 'up']);
        $this->post("/canvases/{$this->canvas->id}/goals/{$third->id}/reorder", ['direction' => 'up']);
        $this->assertSame(['C', 'A', 'B'], $this->goals());

        $this->post("/canvases/{$this->canvas->id}/goals/{$third->id}/reorder", ['direction' => 'down']);
        $this->assertSame(['A', 'C', 'B'], $this->goals());
    }

    public function test_a_deleted_goal_comes_back_through_undo_in_its_place(): void
    {
        $this->goal('A');
        $second = $this->goal('B');
        $this->goal('C');

        $token = $this->delete("/canvases/{$this->canvas->id}/goals/{$second->id}")->assertSessionHas('undo_token')->getSession()->get('undo_token');

        $this->assertSame(['A', 'C'], $this->goals());

        $this->post("/undo/{$token}")->assertRedirect();

        $this->assertSame(['A', 'B', 'C'], $this->goals());
    }

    public function test_a_goal_of_another_canvas_is_a_404(): void
    {
        $goal = $this->goal('A');
        $other = Canvas::factory()->create();

        $this->delete("/canvases/{$other->id}/goals/{$goal->id}")->assertNotFound();
        $this->post("/canvases/{$other->id}/goals/{$goal->id}/reorder", ['direction' => 'up'])->assertNotFound();
    }

    public function test_an_archived_canvas_and_a_non_administrator_are_refused(): void
    {
        $this->canvas->update(['archived_at' => now()]);

        $this->post("/canvases/{$this->canvas->id}/goals", ['title' => 'A'])->assertSessionHasErrors('rule');

        $this->actingAs(User::factory()->create());

        $this->put("/canvases/{$this->canvas->id}/statements/mission", ['body' => 'x'])->assertForbidden();
    }

    public function test_the_goal_kind_is_not_a_text_route(): void
    {
        $this->put("/canvases/{$this->canvas->id}/statements/goal", ['body' => 'x'])->assertNotFound();
    }

    public function test_an_undone_goal_keeps_distinct_positions_after_a_reorder_in_between(): void
    {
        $a = $this->goal('A');
        $b = $this->goal('B');
        $this->goal('C');

        $token = $this->delete("/canvases/{$this->canvas->id}/goals/{$b->id}")->getSession()->get('undo_token');
        $this->post("/canvases/{$this->canvas->id}/goals/{$a->id}/reorder", ['direction' => 'down']);
        $this->post("/undo/{$token}")->assertRedirect();

        $positions = Statement::where('kind', 'goal')->pluck('position')->all();

        $this->assertSame($positions, array_values(array_unique($positions)));
        $this->assertCount(3, $positions);
        $this->assertSame(['C', 'B', 'A'], $this->goals());
    }
}
