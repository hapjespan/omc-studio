<?php

namespace Tests\Feature\Canvas;

use App\Enums\CanvasKind;
use App\Enums\ElementKind;
use App\Enums\StatementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Statement;
use App\Models\User;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SaveAsTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
    }

    private function source(): Canvas
    {
        $source = Canvas::factory()->create(['name' => 'Bron']);
        $area = Area::where('key', 'primary_activities')->firstOrFail();
        $domain = Element::factory()->create(['canvas_id' => $source->id, 'area_id' => $area->id, 'name' => 'Zorg', 'description' => 'Alles']);
        Element::factory()->create(['canvas_id' => $source->id, 'area_id' => $area->id, 'parent_id' => $domain->id, 'kind' => ElementKind::Activity, 'name' => 'Intake', 'rationale' => 'Waarom']);
        Element::factory()->create(['canvas_id' => $source->id, 'area_id' => $area->id, 'name' => 'Oud', 'archived_at' => now()]);
        Statement::create(['canvas_id' => $source->id, 'kind' => StatementKind::Mission, 'title' => 'Missie', 'body' => 'Tekst', 'position' => 0]);
        UnknownAllocationDomain::ensure($source);

        return $source;
    }

    private function save(Canvas $source, string $name): TestResponse
    {
        return $this->actingAs(User::factory()->administrator()->create())->post('/canvases', ['source' => 'save_template', 'canvas_id' => $source->id, 'name' => $name]);
    }

    public function test_the_template_holds_the_active_elements_and_statements_without_an_organisation(): void
    {
        $source = $this->source();

        $this->save($source, 'Kinderopvang basis')->assertRedirect()->assertSessionHasNoErrors();

        $template = Canvas::where('name', 'Kinderopvang basis')->firstOrFail();
        $this->assertSame(CanvasKind::Template, $template->kind);
        $this->assertNull($template->organisation_id);
        $this->assertFalse($template->is_blank_template);
        $this->assertEqualsCanonicalizing(['Zorg', 'Intake'], Element::where('canvas_id', $template->id)->pluck('name')->all());
        $this->assertSame('Waarom', Element::where('canvas_id', $template->id)->where('name', 'Intake')->value('rationale'));
        $this->assertNotNull(Element::where('canvas_id', $template->id)->where('name', 'Intake')->value('parent_id'));
        $this->assertSame('Tekst', Statement::where('canvas_id', $template->id)->value('body'));
    }

    public function test_the_source_canvas_is_left_untouched(): void
    {
        $source = $this->source();

        $this->save($source, 'Sjabloon');

        $this->assertSame(4, Element::where('canvas_id', $source->id)->count());
        $this->assertSame(CanvasKind::Canvas, $source->fresh()->kind);
    }

    public function test_template_names_are_unique_ignoring_case_and_spaces(): void
    {
        $source = $this->source();
        Canvas::factory()->template()->create(['name' => 'Kinderopvang basis']);

        $this->save($source, '  KINDEROPVANG BASIS ')->assertSessionHasErrors('name');

        $this->assertSame(1, Canvas::where('kind', CanvasKind::Template->value)->count());
    }

    public function test_a_reader_is_refused_and_a_template_cannot_be_the_source(): void
    {
        $source = $this->source();
        $template = Canvas::factory()->template()->create();

        $this->actingAs(User::factory()->create())->post('/canvases', ['source' => 'save_template', 'canvas_id' => $source->id, 'name' => 'X'])->assertForbidden();
        $this->save($template, 'Y')->assertSessionHasErrors('canvas_id');
    }
}
