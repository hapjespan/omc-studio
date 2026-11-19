<?php

namespace Tests\Unit\Models;

use App\Enums\StatementKind;
use App\Models\Canvas;
use App\Models\Statement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_statement_holds_kind_title_body_and_position(): void
    {
        $canvas = Canvas::factory()->create();

        $goal = Statement::create([
            'canvas_id' => $canvas->id, 'kind' => StatementKind::Goal,
            'title' => 'Sneller', 'body' => str_repeat('x', 2000), 'position' => 1,
        ]);
        $mission = Statement::create(['canvas_id' => $canvas->id, 'kind' => 'mission', 'body' => 'Zorg', 'position' => 1]);

        $this->assertSame(StatementKind::Goal, $goal->fresh()->kind);
        $this->assertSame(2000, strlen($goal->fresh()->body));
        $this->assertNull($mission->fresh()->title);
    }

    public function test_deleting_a_canvas_removes_its_statements(): void
    {
        $canvas = Canvas::factory()->create();
        Statement::create(['canvas_id' => $canvas->id, 'kind' => 'vision', 'body' => 'Later', 'position' => 1]);

        $canvas->delete();

        $this->assertSame(0, DB::table('statements')->count());
    }

    public function test_the_direction_band_index_exists(): void
    {
        $this->assertTrue(collect(Schema::getIndexes('statements'))->contains(
            fn ($i) => $i['columns'] === ['canvas_id', 'kind', 'position'],
        ));
    }
}
