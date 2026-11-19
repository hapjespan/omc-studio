<?php

namespace Tests\Feature\Finance;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\UndoRecord;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Finance\CostRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CostRecordDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Owner $owner;

    private CostRecord $record;

    private FiscalYear $first;

    private FiscalYear $second;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->organisation = Organisation::factory()->create();
        $this->owner = Owner::factory()->create(['organisation_id' => $this->organisation->id]);
        $this->record = CostRecord::factory()->create([
            'organisation_id' => $this->organisation->id,
            'owner_id' => $this->owner->id,
            'code' => 'LC-001',
        ]);
        $this->first = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2025]);
        $this->second = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);

        foreach ([$this->first, $this->second] as $year) {
            YearlyFigure::factory()->create(['cost_record_id' => $this->record->id, 'fiscal_year_id' => $year->id]);
        }

        $canvases = Canvas::factory()->count(3)->create(['organisation_id' => $this->organisation->id]);

        foreach ([[0, $this->first], [0, $this->second], [1, $this->first], [1, $this->second], [2, $this->first]] as [$index, $year]) {
            $set = AllocationSet::factory()->create([
                'canvas_id' => $canvases[$index]->id,
                'cost_record_id' => $this->record->id,
                'fiscal_year_id' => $year->id,
            ]);

            AllocationLine::factory()->create(['allocation_set_id' => $set->id]);
        }
    }

    public function test_the_counts_name_the_consequence_of_the_deletion(): void
    {
        $counts = app(CostRecords::class)->counts($this->record);

        $this->assertSame(['figures' => 2, 'sets' => 5], $counts);
        $this->assertSame('Verwijdert ook 2 jaarwaarden en 5 toewijzingssets', __('cost_records.delete_consequence', [
            'figures' => trans_choice('cost_records.figure_count', $counts['figures']),
            'sets' => trans_choice('cost_records.set_count', $counts['sets']),
        ]));
    }

    public function test_a_deletion_removes_the_figures_the_sets_and_the_lines_and_hands_out_an_undo_token(): void
    {
        $response = $this->destroy();

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, CostRecord::query()->count());
        $this->assertSame(0, YearlyFigure::query()->count());
        $this->assertSame(0, AllocationSet::query()->count());
        $this->assertSame(0, AllocationLine::query()->count());
        $this->assertSame('Kostenpost verwijderd', session('undo')['message']);
        $this->assertNotNull(UndoRecord::find($this->token()));
    }

    public function test_an_undo_puts_the_cost_record_its_figures_its_sets_and_its_lines_back_with_their_own_identifiers(): void
    {
        $figures = YearlyFigure::query()->orderBy('id')->pluck('id')->all();
        $sets = AllocationSet::query()->orderBy('id')->pluck('id')->all();
        $lines = AllocationLine::query()->orderBy('id')->pluck('allocation_set_id', 'id')->all();

        $this->destroy()->assertSessionHasNoErrors();
        $token = $this->token();
        AuditEntry::query()->delete();

        $this->actingAs($this->administrator)->post('/undo/'.$token)->assertSessionHasNoErrors();

        $this->assertSame('LC-001', CostRecord::findOrFail($this->record->id)->code);
        $this->assertSame($figures, YearlyFigure::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($sets, AllocationSet::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($lines, AllocationLine::query()->orderBy('id')->pluck('allocation_set_id', 'id')->all());
        $this->assertSame(['undone'], AuditEntry::query()->distinct()->pluck('action')->all());
        $this->assertNull(UndoRecord::find($token));
    }

    public function test_a_year_finalised_in_any_canvas_refuses_the_deletion(): void
    {
        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Canvas ICT']);
        FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => $this->second->id]);

        $this->destroy()->assertSessionHasErrors([
            'rule' => '2026 is definitief in canvas Canvas ICT. Heropen het jaar eerst, of archiveer de kostenpost',
        ]);

        $this->assertSame(1, CostRecord::query()->count());
        $this->assertSame(5, AllocationSet::query()->count());
    }

    public function test_an_archived_organisation_refuses_the_deletion(): void
    {
        $this->organisation->update(['archived_at' => now()]);

        $this->destroy()->assertSessionHasErrors(['rule' => 'Deze organisatie is gearchiveerd']);
        $this->assertSame(1, CostRecord::query()->count());
    }

    public function test_an_undo_is_refused_when_the_year_became_finalised_and_restores_nothing(): void
    {
        $this->destroy()->assertSessionHasNoErrors();
        $token = $this->token();

        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Canvas ICT']);
        FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => $this->second->id]);

        $this->actingAs($this->administrator)->post('/undo/'.$token)->assertSessionHasErrors([
            'rule' => '2026 is definitief in canvas Canvas ICT. Heropen het jaar eerst',
        ]);

        $this->assertSame(0, CostRecord::query()->count());
        $this->assertNotNull(UndoRecord::find($token));
    }

    public function test_an_undo_is_refused_when_the_code_was_taken_meanwhile(): void
    {
        $this->destroy()->assertSessionHasNoErrors();
        $token = $this->token();

        CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'owner_id' => $this->owner->id, 'code' => 'lc-001']);

        $this->actingAs($this->administrator)->post('/undo/'.$token)->assertSessionHasErrors([
            'rule' => 'Code: deze code bestaat al in deze organisatie',
        ]);

        $this->assertSame(0, YearlyFigure::query()->count());
    }

    public function test_an_undo_is_refused_when_the_owner_is_gone(): void
    {
        $this->destroy()->assertSessionHasNoErrors();
        $token = $this->token();

        $this->owner->delete();

        $this->actingAs($this->administrator)->post('/undo/'.$token)->assertSessionHasErrors([
            'rule' => 'De rij waar dit onder hoorde bestaat niet meer',
        ]);

        $this->assertSame(0, CostRecord::query()->count());
    }

    public function test_an_undo_is_refused_when_the_organisation_was_archived(): void
    {
        $this->destroy()->assertSessionHasNoErrors();
        $token = $this->token();

        $this->organisation->update(['archived_at' => now()]);

        $this->actingAs($this->administrator)->post('/undo/'.$token)->assertSessionHasErrors([
            'rule' => 'Deze organisatie is gearchiveerd',
        ]);

        $this->assertSame(0, CostRecord::query()->count());
    }

    public function test_archiving_keeps_every_row_and_makes_the_cost_record_and_its_figures_read_only(): void
    {
        $this->actingAs($this->administrator)->post("/cost-records/{$this->record->id}/archive")->assertSessionHasNoErrors();

        $this->assertNotNull($this->record->fresh()->archived_at);
        $this->assertSame(2, YearlyFigure::query()->count());
        $this->assertSame(5, AllocationSet::query()->count());

        $refusal = ['rule' => 'Deze kostenpost is gearchiveerd'];
        $figure = YearlyFigure::query()->where('fiscal_year_id', $this->second->id)->firstOrFail();

        $this->actingAs($this->administrator)->patch("/cost-records/{$this->record->id}", [
            'name' => 'Andere naam', 'cost_kind' => 'other', 'owner_id' => $this->owner->id,
        ])->assertSessionHasErrors($refusal);
        $this->actingAs($this->administrator)->put("/cost-records/{$this->record->id}/years/2026", ['budget' => '100'])->assertSessionHasErrors($refusal);
        $this->actingAs($this->administrator)->delete("/cost-records/{$this->record->id}/years/2026")->assertSessionHasErrors($refusal);

        $this->assertSame($figure->budget_cents, $figure->fresh()->budget_cents);
    }

    public function test_a_restore_makes_the_cost_record_writable_again(): void
    {
        $this->record->update(['archived_at' => now()]);

        $this->actingAs($this->administrator)->post("/cost-records/{$this->record->id}/restore")->assertSessionHasNoErrors();

        $this->assertNull($this->record->fresh()->archived_at);

        $this->actingAs($this->administrator)->patch("/cost-records/{$this->record->id}", [
            'name' => 'Andere naam', 'cost_kind' => 'other', 'owner_id' => $this->owner->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Andere naam', $this->record->fresh()->name);
    }

    public function test_a_reader_is_refused_on_every_route(): void
    {
        $reader = User::factory()->create();

        $this->actingAs($reader)->delete("/cost-records/{$this->record->id}")->assertForbidden();
        $this->actingAs($reader)->post("/cost-records/{$this->record->id}/archive")->assertForbidden();
        $this->actingAs($reader)->post("/cost-records/{$this->record->id}/restore")->assertForbidden();

        $this->assertSame(1, CostRecord::query()->count());
    }

    /** @return TestResponse<Response> */
    private function destroy(): TestResponse
    {
        return $this->actingAs($this->administrator)->delete("/cost-records/{$this->record->id}");
    }

    private function token(): string
    {
        return (string) session('undo')['token'];
    }
}
