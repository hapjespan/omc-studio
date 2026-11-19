<?php

namespace Tests\Feature\Canvas;

use App\Enums\ElementKind;
use App\Enums\StatementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Statement;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateFromTemplateTest extends TestCase
{
    use RefreshDatabase;

    private CanvasLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        $this->lifecycle = app(CanvasLifecycle::class);
    }

    private function template(): Canvas
    {
        $template = Canvas::factory()->template()->create();
        $area = Area::where('key', 'primary_activities')->firstOrFail();
        $domain = Element::factory()->create(['canvas_id' => $template->id, 'area_id' => $area->id, 'name' => 'Zorg', 'description' => 'Alles', 'position' => 2]);
        Element::factory()->create(['canvas_id' => $template->id, 'area_id' => $area->id, 'parent_id' => $domain->id, 'kind' => ElementKind::Activity, 'name' => 'Intake', 'rationale' => 'Waarom']);
        Element::factory()->create(['canvas_id' => $template->id, 'area_id' => $area->id, 'name' => 'Oud', 'archived_at' => now()]);
        Statement::create(['canvas_id' => $template->id, 'kind' => StatementKind::Mission, 'title' => null, 'body' => 'Missie', 'position' => 0]);

        return $template;
    }

    public function test_the_new_canvas_holds_copies_of_the_active_elements_and_statements(): void
    {
        $template = $this->template();
        $organisation = Organisation::factory()->create();

        $canvas = $this->lifecycle->createFromTemplate($organisation, $template, ' Operating model 2027 ');

        $this->assertSame('Operating model 2027', $canvas->name);
        $this->assertSame($organisation->id, $canvas->organisation_id);
        $elements = Element::where('canvas_id', $canvas->id)->where('is_unknown_allocation', false)->get();
        $this->assertEqualsCanonicalizing(['Zorg', 'Intake'], $elements->pluck('name')->all());
        $domain = $elements->firstWhere('name', 'Zorg');
        $activity = $elements->firstWhere('name', 'Intake');
        $this->assertSame($domain->id, $activity->parent_id);
        $this->assertNotSame($template->elements()->where('name', 'Zorg')->value('id'), $domain->id);
        $this->assertSame('Alles', $domain->description);
        $this->assertSame(2, $domain->position);
        $this->assertSame('Waarom', $activity->rationale);
        $this->assertSame('Missie', Statement::where('canvas_id', $canvas->id)->value('body'));
    }

    public function test_the_canvas_holds_the_unknown_allocation_domain_once(): void
    {
        $canvas = $this->lifecycle->createFromTemplate(Organisation::factory()->create(), $this->template(), 'Nieuw');

        $this->assertSame(1, Element::where('canvas_id', $canvas->id)->where('is_unknown_allocation', true)->count());
    }

    public function test_a_later_change_to_the_template_leaves_the_canvas_unchanged(): void
    {
        $template = $this->template();
        $canvas = $this->lifecycle->createFromTemplate(Organisation::factory()->create(), $template, 'Nieuw');

        Element::where('canvas_id', $template->id)->where('name', 'Zorg')->firstOrFail()->update(['name' => 'Gewijzigd']);

        $this->assertTrue(Element::where('canvas_id', $canvas->id)->where('name', 'Zorg')->exists());
    }

    public function test_a_name_in_use_ignoring_case_and_spaces_is_refused(): void
    {
        $organisation = Organisation::factory()->create();
        $this->lifecycle->createFromTemplate($organisation, $this->template(), 'Model');

        try {
            $this->lifecycle->createFromTemplate($organisation, $this->template(), ' model ');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Naam: dit canvas bestaat al in deze organisatie', $e->errors()['name'][0]);
        }

        $this->assertSame(1, Canvas::where('organisation_id', $organisation->id)->count());
    }

    public function test_the_same_name_is_free_in_another_organisation(): void
    {
        $template = $this->template();
        $this->lifecycle->createFromTemplate(Organisation::factory()->create(), $template, 'Model');
        $second = $this->lifecycle->createFromTemplate(Organisation::factory()->create(), $template, 'Model');

        $this->assertSame('Model', $second->name);
    }

    public function test_an_archived_organisation_is_refused(): void
    {
        $organisation = Organisation::factory()->create(['archived_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->lifecycle->createFromTemplate($organisation, $this->template(), 'Model');
    }
}
