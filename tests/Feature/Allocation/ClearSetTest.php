<?php

namespace Tests\Feature\Allocation;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\UndoRecord;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ClearSetTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Canvas $canvas;

    private CostRecord $record;

    private FiscalYear $year;

    private Element $first;

    private Element $second;

    private ?User $administrator = null;

    protected function setUp(): void
    {
        parent::setUp();

        app(ReferenceDataStep::class)();

        $this->organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id]);
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
        $this->record = CostRecord::factory()->create([
            'organisation_id' => $this->organisation->id,
            'owner_id' => Owner::factory()->create(['organisation_id' => $this->organisation->id])->id,
        ]);
        YearlyFigure::factory()->create(['cost_record_id' => $this->record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 23_000]);
        $this->first = $this->element('Offreren');
        $this->second = $this->element('Bestellen');
        $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $this->record->id, 'fiscal_year_id' => $this->year->id]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $this->first->id, 'basis_points' => 6000, 'position' => 0]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $this->second->id, 'basis_points' => 4000, 'position' => 1]);
    }

    private function element(string $name): Element
    {
        return Element::factory()->create([
            'canvas_id' => $this->canvas->id,
            'area_id' => Area::query()->where('key', 'primary_activities')->firstOrFail()->id,
            'kind' => ElementKind::Domain,
            'name' => $name,
        ]);
    }

    private function admin(): static
    {
        return $this->actingAs($this->administrator ??= User::factory()->administrator()->create());
    }

    /** @return TestResponse<Response> */
    private function clear(): TestResponse
    {
        return $this->admin()->delete("/canvases/{$this->canvas->id}/years/2026/cost-records/{$this->record->id}/allocation");
    }

    private function clearWithToken(): string
    {
        $this->clear()->assertSessionHasNoErrors();

        return (string) session('undo')['token'];
    }

    /** @return list<array{string, int}> */
    private function stored(): array
    {
        return AllocationLine::query()->with('element')->orderBy('position')
            ->get()->map(fn (AllocationLine $line) => [(string) $line->element?->name, $line->basis_points])->all();
    }

    public function test_clearing_removes_the_whole_set_and_offers_undo(): void
    {
        $this->clear()->assertSessionHasNoErrors();

        $this->assertSame(0, AllocationSet::count());
        $this->assertSame(0, AllocationLine::count());
        $this->assertSame('Verdeling gewist', session('undo')['message']);
        $this->assertSame(1, UndoRecord::count());
        $this->assertSame(1, AuditEntry::query()->where('subject_type', 'allocation_set')->where('action', 'deleted')->count());
    }

    public function test_undo_restores_set_and_lines_with_their_identifiers(): void
    {
        $setId = AllocationSet::firstOrFail()->id;
        $lineIds = AllocationLine::orderBy('position')->pluck('id')->all();

        $token = $this->clearWithToken();
        $this->admin()->post('/undo/'.$token)->assertSessionHasNoErrors();

        $this->assertSame($setId, AllocationSet::firstOrFail()->id);
        $this->assertSame($lineIds, AllocationLine::orderBy('position')->pluck('id')->all());
        $this->assertSame([['Offreren', 6000], ['Bestellen', 4000]], $this->stored());
        $this->assertSame(0, UndoRecord::count());
    }

    public function test_clearing_is_refused_for_an_archived_canvas_and_a_finalised_year(): void
    {
        FinalisedYear::factory()->create(['canvas_id' => $this->canvas->id, 'fiscal_year_id' => $this->year->id]);
        $this->clear()->assertSessionHasErrors(['rule' => '2026 is definitief. Heropen het jaar eerst']);

        FinalisedYear::query()->delete();
        $this->canvas->forceFill(['archived_at' => now()])->save();
        $this->clear()->assertSessionHasErrors(['rule' => 'Dit canvas is gearchiveerd']);

        $this->assertSame(2, AllocationLine::count());
    }

    public function test_clearing_without_a_set_is_refused(): void
    {
        AllocationLine::query()->delete();
        AllocationSet::query()->delete();

        $this->clear()->assertSessionHasErrors(['rule' => 'Deze kostenpost heeft geen verdeling in 2026']);
    }

    public function test_the_route_is_for_administrators_only(): void
    {
        $this->actingAs(User::factory()->create())
            ->delete("/canvases/{$this->canvas->id}/years/2026/cost-records/{$this->record->id}/allocation")
            ->assertForbidden();

        $this->assertSame(1, AllocationSet::count());
    }

    public function test_undo_is_refused_when_a_new_set_was_created_for_the_same_record_canvas_and_year(): void
    {
        $token = $this->clearWithToken();
        $this->admin()->put("/canvases/{$this->canvas->id}/years/2026/cost-records/{$this->record->id}/allocation", [
            'lines' => [['element_id' => $this->first->id, 'basis_points' => 10_000]],
        ])->assertSessionHasNoErrors();

        $this->admin()->post('/undo/'.$token)->assertSessionHasErrors(['rule' => 'Er is inmiddels een nieuwe verdeling voor deze kostenpost en dit jaar']);

        $this->assertSame(1, AllocationSet::count());
        $this->assertSame([['Offreren', 10_000]], $this->stored());
        $this->assertSame(1, UndoRecord::count());
    }

    public function test_undo_is_refused_when_the_year_was_finalised_or_a_target_archived_meanwhile(): void
    {
        $token = $this->clearWithToken();
        $finalised = FinalisedYear::factory()->create(['canvas_id' => $this->canvas->id, 'fiscal_year_id' => $this->year->id]);
        $this->admin()->post('/undo/'.$token)->assertSessionHasErrors(['rule' => '2026 is definitief. Heropen het jaar eerst']);

        $finalised->delete();
        $this->second->forceFill(['archived_at' => now()])->save();
        $this->admin()->post('/undo/'.$token)->assertSessionHasErrors(['rule' => 'Doel Bestellen kan geen kosten dragen']);

        $this->assertSame(0, AllocationSet::count());
        $this->assertSame(1, UndoRecord::count());
    }

    public function test_undo_is_refused_when_the_figures_or_the_canvas_state_changed(): void
    {
        $token = $this->clearWithToken();
        YearlyFigure::query()->delete();
        $this->admin()->post('/undo/'.$token)->assertSessionHasErrors(['rule' => 'Geen jaarwaarden in 2026 voor deze kostenpost']);

        $this->canvas->forceFill(['archived_at' => now()])->save();
        $this->admin()->post('/undo/'.$token)->assertSessionHasErrors(['rule' => 'Dit canvas is gearchiveerd']);

        $this->assertSame(0, AllocationSet::count());
    }
}
