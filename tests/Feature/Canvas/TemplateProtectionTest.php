<?php

namespace Tests\Feature\Canvas;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Operations\Steps\BlankTemplateStep;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        (new BlankTemplateStep)();
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_a_template_is_renamed_with_names_unique_ignoring_case(): void
    {
        $template = Canvas::factory()->template()->create(['name' => 'Eerste']);
        Canvas::factory()->template()->create(['name' => 'Tweede']);

        $this->admin()->patch("/templates/{$template->id}", ['name' => ' tweede '])->assertSessionHasErrors('name');
        $this->admin()->patch("/templates/{$template->id}", ['name' => 'EERSTE'])->assertSessionHasNoErrors();
        $this->admin()->patch("/templates/{$template->id}", ['name' => 'Derde'])->assertSessionHasNoErrors();

        $this->assertSame('Derde', $template->fresh()->name);
    }

    public function test_deletion_needs_the_typed_name_and_leaves_started_canvases_alone(): void
    {
        $template = Canvas::factory()->template()->create(['name' => 'Basis']);
        Element::factory()->create(['canvas_id' => $template->id]);
        $canvas = Canvas::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        Element::factory()->create(['canvas_id' => $canvas->id]);

        $this->admin()->delete("/templates/{$template->id}", ['confirmation' => 'basis'])->assertSessionHasErrors('confirmation');
        $this->assertNotNull($template->fresh());

        $this->admin()->delete("/templates/{$template->id}", ['confirmation' => 'Basis'])->assertRedirect('/overview');

        $this->assertNull($template->fresh());
        $this->assertSame(0, Element::where('canvas_id', $template->id)->count());
        $this->assertSame(1, Element::where('canvas_id', $canvas->id)->count());
    }

    public function test_the_blank_template_is_refused_for_rename_and_delete(): void
    {
        $blank = Canvas::where('is_blank_template', true)->firstOrFail();

        $this->admin()->patch("/templates/{$blank->id}", ['name' => 'Anders'])->assertSessionHasErrors('rule');
        $this->admin()->delete("/templates/{$blank->id}", ['confirmation' => $blank->name])->assertSessionHasErrors('rule');

        $this->assertSame($blank->name, $blank->fresh()->name);
    }

    public function test_a_canvas_is_not_a_template_and_a_reader_is_refused(): void
    {
        $canvas = Canvas::factory()->create();
        $template = Canvas::factory()->template()->create();

        $this->admin()->patch("/templates/{$canvas->id}", ['name' => 'X'])->assertNotFound();
        $this->actingAs(User::factory()->create())->delete("/templates/{$template->id}", ['confirmation' => $template->name])->assertForbidden();
    }
}
