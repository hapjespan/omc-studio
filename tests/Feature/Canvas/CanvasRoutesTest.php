<?php

namespace Tests\Feature\Canvas;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanvasRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_a_canvas_is_started_from_a_template_and_entry_opens(): void
    {
        $organisation = Organisation::factory()->create();
        $template = Canvas::factory()->template()->create();
        Element::factory()->create(['canvas_id' => $template->id]);

        $response = $this->admin()->post('/canvases', ['source' => 'template', 'template_id' => $template->id, 'organisation_id' => $organisation->id, 'name' => 'Operating model 2027']);

        $canvas = Canvas::where('organisation_id', $organisation->id)->firstOrFail();
        $response->assertRedirect('/canvases/'.$canvas->id.'/entry');
        $this->assertSame(2, Element::where('canvas_id', $canvas->id)->count());
    }

    public function test_a_canvas_is_copied_to_another_organisation(): void
    {
        $source = Canvas::factory()->create();
        $target = Organisation::factory()->create();

        $this->admin()->post('/canvases', ['source' => 'copy', 'canvas_id' => $source->id, 'organisation_id' => $target->id, 'name' => 'Kopie'])->assertRedirect();

        $this->assertSame($target->id, Canvas::where('name', 'Kopie')->firstOrFail()->organisation_id);
    }

    public function test_a_taken_name_is_an_error_on_name(): void
    {
        $existing = Canvas::factory()->create(['name' => 'Model']);
        $template = Canvas::factory()->template()->create();

        $this->admin()->post('/canvases', ['source' => 'template', 'template_id' => $template->id, 'organisation_id' => $existing->organisation_id, 'name' => ' model '])
            ->assertSessionHasErrors(['name' => 'Naam: dit canvas bestaat al in deze organisatie']);
    }

    public function test_invalid_input_is_refused(): void
    {
        $organisation = Organisation::factory()->create();
        $plain = Canvas::factory()->create();

        $this->admin()->post('/canvases', ['source' => 'template', 'template_id' => $plain->id, 'organisation_id' => $organisation->id, 'name' => 'X'])->assertSessionHasErrors('template_id');
        $this->admin()->post('/canvases', ['source' => 'nonsense', 'organisation_id' => $organisation->id, 'name' => 'X'])->assertSessionHasErrors('source');
        $this->admin()->post('/canvases', ['source' => 'copy', 'canvas_id' => $plain->id, 'organisation_id' => $organisation->id, 'name' => ''])->assertSessionHasErrors('name');
        $this->admin()->post('/canvases', ['source' => 'copy', 'canvas_id' => $plain->id, 'organisation_id' => $organisation->id, 'name' => str_repeat('a', 151)])->assertSessionHasErrors('name');
    }

    public function test_rename_archive_restore_and_delete(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Oud']);

        $this->admin()->patch('/canvases/'.$canvas->id, ['name' => 'Nieuw'])->assertRedirect();
        $this->assertSame('Nieuw', $canvas->fresh()->name);

        $this->admin()->post('/canvases/'.$canvas->id.'/archive')->assertRedirect();
        $this->assertNotNull($canvas->fresh()->archived_at);

        $this->admin()->post('/canvases/'.$canvas->id.'/restore')->assertRedirect();
        $this->assertNull($canvas->fresh()->archived_at);

        $this->admin()->delete('/canvases/'.$canvas->id, ['confirmation' => 'nieuw'])->assertSessionHasErrors('confirmation');
        $this->assertNotNull($canvas->fresh());

        $this->admin()->delete('/canvases/'.$canvas->id, ['confirmation' => 'Nieuw'])->assertRedirect('/overview');
        $this->assertNull(Canvas::find($canvas->id));
    }

    public function test_a_reader_gets_403_on_each_route(): void
    {
        $canvas = Canvas::factory()->create();
        $template = Canvas::factory()->template()->create();
        $reader = User::factory()->create();

        $this->actingAs($reader)->post('/canvases', ['source' => 'template', 'template_id' => $template->id, 'organisation_id' => $canvas->organisation_id, 'name' => 'X'])->assertForbidden();
        $this->actingAs($reader)->patch('/canvases/'.$canvas->id, ['name' => 'X'])->assertForbidden();
        $this->actingAs($reader)->post('/canvases/'.$canvas->id.'/archive')->assertForbidden();
        $this->actingAs($reader)->post('/canvases/'.$canvas->id.'/restore')->assertForbidden();
        $this->actingAs($reader)->delete('/canvases/'.$canvas->id, ['confirmation' => $canvas->name])->assertForbidden();
        $this->assertNotNull($canvas->fresh());
    }
}
