<?php

namespace Tests\Feature\Canvas;

use App\Enums\ElementKind;
use App\Enums\StatementKind;
use App\Models\Area;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Statement;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeleteCanvasTest extends TestCase
{
    use RefreshDatabase;

    private CanvasLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        $this->actingAs(User::factory()->create());
        $this->lifecycle = app(CanvasLifecycle::class);
    }

    private function filled(string $name, ?Canvas $sameOrganisationAs = null): Canvas
    {
        $canvas = Canvas::factory()->create([
            'name' => $name,
            ...($sameOrganisationAs ? ['organisation_id' => $sameOrganisationAs->organisation_id] : []),
        ]);
        $area = Area::where('key', 'primary_activities')->firstOrFail();
        $domain = Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => $area->id]);
        $cluster = Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => $area->id, 'parent_id' => $domain->id, 'kind' => ElementKind::Cluster]);
        Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => $area->id, 'parent_id' => $cluster->id, 'kind' => ElementKind::Activity]);
        UnknownAllocationDomain::ensure($canvas);
        Statement::create(['canvas_id' => $canvas->id, 'kind' => StatementKind::Goal, 'title' => 'Doel', 'body' => 'Tekst', 'position' => 0]);
        Statement::create(['canvas_id' => $canvas->id, 'kind' => StatementKind::Mission, 'title' => null, 'body' => 'Missie', 'position' => 1]);

        return $canvas;
    }

    public function test_the_counts_of_the_dialog_match_what_the_deletion_removes(): void
    {
        $canvas = $this->filled('Operating model 2027');

        $counts = $this->lifecycle->deletionCounts($canvas);

        $this->assertSame(['elements' => 4, 'statements' => 2], $counts);

        $this->lifecycle->delete($canvas, 'Operating model 2027');

        $this->assertSame(0, Element::where('canvas_id', $canvas->id)->count());
        $this->assertSame(0, Statement::where('canvas_id', $canvas->id)->count());
        $this->assertSame([$counts['elements'], null], AuditEntry::where('canvas_id', $canvas->id)->sole()->changes['elements']);
        $this->assertSame([$counts['statements'], null], AuditEntry::where('canvas_id', $canvas->id)->sole()->changes['statements']);
    }

    public function test_a_name_that_is_not_typed_exactly_removes_nothing(): void
    {
        $canvas = $this->filled('Operating model 2027');
        $entries = AuditEntry::where('canvas_id', $canvas->id)->count();

        foreach (['operating model 2027', ' Operating model 2027', 'Operating model 2027 ', 'Operating model', ''] as $typed) {
            try {
                $this->lifecycle->delete($canvas, $typed);
                $this->fail('expected refusal of '.$typed);
            } catch (ValidationException $e) {
                $this->assertSame('Typ de naam van het canvas precies zoals hij er staat', $e->errors()['confirmation'][0]);
            }
        }

        $this->assertSame(1, Canvas::whereKey($canvas->id)->count());
        $this->assertSame(4, Element::where('canvas_id', $canvas->id)->count());
        $this->assertSame(2, Statement::where('canvas_id', $canvas->id)->count());
        $this->assertSame($entries, AuditEntry::where('canvas_id', $canvas->id)->count());
    }

    public function test_the_exact_name_removes_the_canvas_with_its_content_and_its_log(): void
    {
        $canvas = $this->filled('Operating model 2027');
        $other = $this->filled('Operating model 2028', $canvas);
        $elsewhere = AuditEntry::where('canvas_id', $other->id)->count();
        $organisationEntries = AuditEntry::whereNull('canvas_id')->where('organisation_id', $canvas->organisation_id)->count();

        $this->lifecycle->delete($canvas, 'Operating model 2027');

        $this->assertSame(0, Canvas::whereKey($canvas->id)->count());
        $this->assertSame(0, Element::where('canvas_id', $canvas->id)->count());
        $this->assertSame(0, Statement::where('canvas_id', $canvas->id)->count());
        $this->assertSame(1, Canvas::whereKey($other->id)->count());
        $this->assertSame(4, Element::where('canvas_id', $other->id)->count());
        $this->assertSame(2, Statement::where('canvas_id', $other->id)->count());
        $this->assertSame($elsewhere, AuditEntry::where('canvas_id', $other->id)->count());
        $this->assertSame($organisationEntries, AuditEntry::whereNull('canvas_id')->where('organisation_id', $canvas->organisation_id)->count());
    }

    public function test_one_entry_remains_with_actor_name_and_counts(): void
    {
        $canvas = $this->filled('Operating model 2027');
        $entriesBefore = AuditEntry::where('canvas_id', $canvas->id)->count();
        $start = now()->startOfSecond();

        $this->lifecycle->delete($canvas, 'Operating model 2027');

        $entry = AuditEntry::where('canvas_id', $canvas->id)->sole();

        $this->assertSame('canvas', $entry->subject_type);
        $this->assertSame('deleted', $entry->action);
        $this->assertSame('Operating model 2027', $entry->subject_label);
        $this->assertSame($canvas->organisation_id, $entry->organisation_id);
        $this->assertNotNull($entry->actor_id);
        $this->assertTrue($entry->occurred_at->greaterThanOrEqualTo($start));
        $this->assertSame([
            'name' => ['Operating model 2027', null],
            'elements' => [4, null],
            'statements' => [2, null],
            'audit_entries' => [$entriesBefore, null],
        ], $entry->changes);
    }

    public function test_the_deletion_offers_no_undo(): void
    {
        $canvas = $this->filled('Operating model 2027');

        $this->lifecycle->delete($canvas, 'Operating model 2027');

        $this->assertSame(0, UndoRecord::query()->count());
    }

    public function test_a_template_is_refused(): void
    {
        $template = Canvas::factory()->template()->create(['name' => 'Leeg canvas']);

        try {
            $this->lifecycle->delete($template, 'Leeg canvas');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Een sjabloon bevat alleen structuur en Koers', $e->errors()['rule'][0]);
        }

        $this->assertSame(1, Canvas::whereKey($template->id)->count());
    }

    public function test_an_archived_canvas_is_deleted(): void
    {
        $canvas = $this->filled('Operating model 2027');
        $this->lifecycle->archive($canvas);

        $this->lifecycle->delete($canvas, 'Operating model 2027');

        $this->assertSame(0, Canvas::whereKey($canvas->id)->count());
        $this->assertSame(1, AuditEntry::where('canvas_id', $canvas->id)->count());
    }
}
