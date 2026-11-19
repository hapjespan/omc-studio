<?php

namespace Tests\Feature\Canvas;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Application;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\SystemNotice;
use App\Models\User;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\OrganisationService;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CanvasDeleteD2Test extends TestCase
{
    use RefreshDatabase;

    private CanvasLifecycle $lifecycle;

    private OrganisationService $organisations;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        $this->actingAs(User::factory()->create());
        $this->lifecycle = app(CanvasLifecycle::class);
        $this->organisations = app(OrganisationService::class);
        $this->organisation = Organisation::factory()->create(['name' => 'Stichting Noord']);
    }

    private function allocated(string $name): Canvas
    {
        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => $name]);
        $element = Element::factory()->create(['canvas_id' => $canvas->id, 'name' => $name.' doel']);
        UnknownAllocationDomain::ensure($canvas);

        $year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id]);
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id]);
        $set = AllocationSet::factory()->create(['canvas_id' => $canvas->id, 'cost_record_id' => $record->id, 'fiscal_year_id' => $year->id]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $element->id, 'basis_points' => 10000, 'position' => 0]);
        FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => $year->id]);
        SystemNotice::factory()->create(['canvas_id' => $canvas->id]);

        return $canvas;
    }

    public function test_the_dialog_counts_name_the_sets_and_the_finalised_years(): void
    {
        $canvas = $this->allocated('Model 2027');

        $this->assertSame(
            ['elements' => 2, 'statements' => 0, 'allocation_sets' => 1, 'finalised_years' => 1],
            $this->lifecycle->deletionCounts($canvas),
        );
    }

    public function test_deletion_removes_the_sets_lines_finalised_years_and_notices_of_that_canvas_only(): void
    {
        $canvas = $this->allocated('Model 2027');
        $other = $this->allocated('Model 2028');
        $counts = $this->lifecycle->deletionCounts($canvas);

        $this->lifecycle->delete($canvas, 'Model 2027');

        $this->assertSame(0, Canvas::whereKey($canvas->id)->count());
        $this->assertSame(0, AllocationSet::where('canvas_id', $canvas->id)->count());
        $this->assertSame(0, FinalisedYear::where('canvas_id', $canvas->id)->count());
        $this->assertSame(0, SystemNotice::where('canvas_id', $canvas->id)->count());
        $this->assertSame(1, AllocationLine::query()->count());

        $this->assertSame(1, AllocationSet::where('canvas_id', $other->id)->count());
        $this->assertSame(1, FinalisedYear::where('canvas_id', $other->id)->count());
        $this->assertSame(1, SystemNotice::where('canvas_id', $other->id)->count());

        $entry = AuditEntry::where('canvas_id', $canvas->id)->sole();

        $this->assertSame([$counts['allocation_sets'], null], $entry->changes['allocation_sets']);
        $this->assertSame([$counts['finalised_years'], null], $entry->changes['finalised_years']);
    }

    public function test_the_cost_records_of_the_organisation_survive_the_deletion_of_a_canvas(): void
    {
        $canvas = $this->allocated('Model 2027');

        $this->lifecycle->delete($canvas, 'Model 2027');

        $this->assertSame(1, CostRecord::where('organisation_id', $this->organisation->id)->count());
        $this->assertSame(1, FiscalYear::where('organisation_id', $this->organisation->id)->count());
    }

    public function test_organisation_deletion_is_refused_while_a_cost_record_exists(): void
    {
        CostRecord::factory()->create(['organisation_id' => $this->organisation->id]);

        try {
            $this->organisations->delete($this->organisation);
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Deze organisatie heeft nog canvassen of kostenposten. Archiveer haar, of verwijder die eerst', $e->errors()['rule'][0]);
        }

        $this->assertSame(1, Organisation::whereKey($this->organisation->id)->count());
    }

    public function test_organisation_deletion_removes_its_owners_applications_and_fiscal_years(): void
    {
        $kept = Organisation::factory()->create(['name' => 'Stichting Zuid']);
        Owner::factory()->create(['organisation_id' => $kept->id]);
        Application::factory()->create(['organisation_id' => $kept->id]);
        FiscalYear::factory()->create(['organisation_id' => $kept->id]);

        Owner::factory()->create(['organisation_id' => $this->organisation->id]);
        Application::factory()->create(['organisation_id' => $this->organisation->id]);
        FiscalYear::factory()->create(['organisation_id' => $this->organisation->id]);

        $this->organisations->delete($this->organisation);

        $this->assertSame(0, Owner::where('organisation_id', $this->organisation->id)->count());
        $this->assertSame(0, Application::where('organisation_id', $this->organisation->id)->count());
        $this->assertSame(0, FiscalYear::where('organisation_id', $this->organisation->id)->count());

        $this->assertSame(1, Owner::where('organisation_id', $kept->id)->count());
        $this->assertSame(1, Application::where('organisation_id', $kept->id)->count());
        $this->assertSame(1, FiscalYear::where('organisation_id', $kept->id)->count());
    }
}
