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
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CopyCanvasTest extends TestCase
{
    use RefreshDatabase;

    private CanvasLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        $this->lifecycle = app(CanvasLifecycle::class);
    }

    private function source(): Canvas
    {
        $source = Canvas::factory()->create(['name' => 'Bron']);
        $area = Area::where('key', 'primary_activities')->firstOrFail();
        $domain = Element::factory()->create(['canvas_id' => $source->id, 'area_id' => $area->id, 'name' => 'Zorg', 'description' => 'Alles']);
        $cluster = Element::factory()->create(['canvas_id' => $source->id, 'area_id' => $area->id, 'parent_id' => $domain->id, 'kind' => ElementKind::Cluster, 'name' => 'Groep']);
        Element::factory()->create(['canvas_id' => $source->id, 'area_id' => $area->id, 'parent_id' => $cluster->id, 'kind' => ElementKind::Activity, 'name' => 'Intake', 'rationale' => 'Waarom']);
        Element::factory()->create(['canvas_id' => $source->id, 'area_id' => $area->id, 'name' => 'Oud', 'archived_at' => now()]);
        Statement::create(['canvas_id' => $source->id, 'kind' => StatementKind::Goal, 'title' => 'Doel', 'body' => 'Tekst', 'position' => 0]);
        UnknownAllocationDomain::ensure($source);

        return $source;
    }

    public function test_a_copy_within_the_organisation_holds_the_active_content_with_new_identifiers(): void
    {
        $source = $this->source();

        $copy = $this->lifecycle->copy($source, $source->organisation, 'Kopie');

        $this->assertSame($source->organisation_id, $copy->organisation_id);
        $names = Element::where('canvas_id', $copy->id)->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Zorg', 'Groep', 'Intake', 'Onbekende allocatie'], $names);
        $this->assertSame([], Element::where('canvas_id', $copy->id)->whereIn('id', Element::where('canvas_id', $source->id)->pluck('id'))->pluck('id')->all());
        $this->assertSame('Tekst', Statement::where('canvas_id', $copy->id)->value('body'));
    }

    public function test_parents_are_mapped_to_the_copied_elements(): void
    {
        $source = $this->source();

        $copy = $this->lifecycle->copy($source, $source->organisation, 'Kopie');

        $byName = Element::where('canvas_id', $copy->id)->get()->keyBy('name');
        $this->assertNull($byName['Zorg']->parent_id);
        $this->assertSame($byName['Zorg']->id, $byName['Groep']->parent_id);
        $this->assertSame($byName['Groep']->id, $byName['Intake']->parent_id);
        $this->assertSame('Waarom', $byName['Intake']->rationale);
    }

    public function test_archived_elements_are_not_copied_and_the_unknown_domain_exists_once(): void
    {
        $source = $this->source();

        $copy = $this->lifecycle->copy($source, $source->organisation, 'Kopie');

        $this->assertFalse(Element::where('canvas_id', $copy->id)->where('name', 'Oud')->exists());
        $this->assertSame(1, Element::where('canvas_id', $copy->id)->where('is_unknown_allocation', true)->count());
    }

    public function test_a_copy_to_another_organisation_holds_the_same_structure(): void
    {
        $source = $this->source();
        $target = Organisation::factory()->create();

        $copy = $this->lifecycle->copy($source, $target, 'Bron');

        $this->assertSame($target->id, $copy->organisation_id);
        $this->assertSame(4, Element::where('canvas_id', $copy->id)->count());
        $this->assertSame(1, Statement::where('canvas_id', $copy->id)->count());
    }

    public function test_the_source_is_untouched(): void
    {
        $source = $this->source();
        $before = Element::where('canvas_id', $source->id)->count();

        $this->lifecycle->copy($source, $source->organisation, 'Kopie');

        $this->assertSame($before, Element::where('canvas_id', $source->id)->count());
    }

    public function test_a_name_in_use_in_the_target_is_refused(): void
    {
        $source = $this->source();

        try {
            $this->lifecycle->copy($source, $source->organisation, ' BRON');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Naam: dit canvas bestaat al in deze organisatie', $e->errors()['name'][0]);
        }
    }

    public function test_an_archived_target_organisation_is_refused(): void
    {
        $target = Organisation::factory()->create(['archived_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->lifecycle->copy($this->source(), $target, 'Kopie');
    }
}
