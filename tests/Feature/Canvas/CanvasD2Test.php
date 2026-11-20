<?php

namespace Tests\Feature\Canvas;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanvasD2Test extends TestCase
{
    use RefreshDatabase;

    private CanvasLifecycle $lifecycle;

    private Organisation $organisation;

    private Canvas $source;

    private CostRecord $record;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        $this->actingAs(User::factory()->create());
        $this->lifecycle = app(CanvasLifecycle::class);

        $this->organisation = Organisation::factory()->create(['name' => 'Stichting Noord']);
        $this->source = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Bron']);

        $area = Area::where('key', 'primary_activities')->firstOrFail();
        $domain = Element::factory()->create(['canvas_id' => $this->source->id, 'area_id' => $area->id, 'name' => 'Zorg']);
        $cluster = Element::factory()->create(['canvas_id' => $this->source->id, 'area_id' => $area->id, 'parent_id' => $domain->id, 'kind' => ElementKind::Cluster, 'name' => 'Groep']);
        $activity = Element::factory()->create(['canvas_id' => $this->source->id, 'area_id' => $area->id, 'parent_id' => $cluster->id, 'kind' => ElementKind::Activity, 'name' => 'Intake']);
        Element::factory()->create(['canvas_id' => $this->source->id, 'area_id' => $area->id, 'name' => 'Oud', 'archived_at' => now()]);
        UnknownAllocationDomain::ensure($this->source);

        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2027]);
        $this->record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Licenties']);

        $set = AllocationSet::factory()->create([
            'canvas_id' => $this->source->id,
            'cost_record_id' => $this->record->id,
            'fiscal_year_id' => $this->year->id,
        ]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $activity->id, 'basis_points' => 7000, 'position' => 0]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $cluster->id, 'basis_points' => 3000, 'position' => 1]);

        FinalisedYear::factory()->create(['canvas_id' => $this->source->id, 'fiscal_year_id' => $this->year->id]);
    }

    public function test_a_copy_within_the_organisation_holds_the_sets_with_their_line_order_on_the_copied_elements(): void
    {
        $copy = $this->lifecycle->copy($this->source, $this->organisation, 'Kopie');

        $set = AllocationSet::with('lines')->where('canvas_id', $copy->id)->sole();

        $this->assertNotSame(AllocationSet::where('canvas_id', $this->source->id)->value('id'), $set->id);
        $this->assertSame($this->record->id, $set->cost_record_id);
        $this->assertSame($this->year->id, $set->fiscal_year_id);

        $names = Element::whereIn('id', $set->lines->pluck('element_id'))->pluck('name', 'id');

        $this->assertSame([['Intake', 7000, 0], ['Groep', 3000, 1]], $set->lines->map(fn (AllocationLine $line) => [$names[$line->element_id], $line->basis_points, $line->position])->all());
        $this->assertSame([], Element::whereIn('id', $set->lines->pluck('element_id'))->where('canvas_id', '!=', $copy->id)->pluck('id')->all());
    }

    public function test_a_copy_holds_no_finalised_year_and_no_archived_element(): void
    {
        $copy = $this->lifecycle->copy($this->source, $this->organisation, 'Kopie');

        $this->assertSame(0, FinalisedYear::where('canvas_id', $copy->id)->count());
        $this->assertFalse(Element::where('canvas_id', $copy->id)->where('name', 'Oud')->exists());
    }

    public function test_a_copy_to_another_organisation_holds_no_sets_so_every_cost_record_sits_in_unknown_allocation(): void
    {
        $target = Organisation::factory()->create(['name' => 'Stichting Zuid']);

        $copy = $this->lifecycle->copy($this->source, $target, 'Kopie');

        $this->assertSame(0, AllocationSet::where('canvas_id', $copy->id)->count());
        $this->assertSame(1, Element::where('canvas_id', $copy->id)->where('is_unknown_allocation', true)->count());
    }

    public function test_a_canvas_started_from_a_template_holds_no_sets(): void
    {
        $template = Canvas::factory()->template()->create(['name' => 'Kinderopvang basis']);
        Element::factory()->create(['canvas_id' => $template->id, 'area_id' => Area::where('key', 'primary_activities')->firstOrFail()->id, 'name' => 'Zorg']);

        $canvas = $this->lifecycle->createFromTemplate($this->organisation, $template, 'Nieuw');

        $this->assertSame(0, AllocationSet::where('canvas_id', $canvas->id)->count());
    }
}
