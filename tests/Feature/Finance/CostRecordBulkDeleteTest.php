<?php

namespace Tests\Feature\Finance;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\UndoRecord;
use App\Models\User;
use App\Models\YearlyFigure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CostRecordBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private const FINALISED_REFUSAL = '2026 is definitief in canvas Strategie. Heropen het jaar eerst, of archiveer de kostenpost';

    private Organisation $organisation;

    private Owner $owner;

    private FiscalYear $open;

    private FiscalYear $finalised;

    private CostRecord $first;

    private CostRecord $second;

    private CostRecord $blocked;

    private ?User $administrator = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->owner = Owner::factory()->create(['organisation_id' => $this->organisation->id]);
        $this->open = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2025]);
        $this->finalised = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);

        $this->first = $this->record('Een');
        $this->second = $this->record('Twee');
        $this->blocked = $this->record('Drie');

        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Strategie']);
        FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => $this->finalised->id]);

        YearlyFigure::factory()->create(['cost_record_id' => $this->blocked->id, 'fiscal_year_id' => $this->finalised->id]);
        YearlyFigure::factory()->create(['cost_record_id' => $this->first->id, 'fiscal_year_id' => $this->open->id]);

        $set = AllocationSet::factory()->create([
            'canvas_id' => Canvas::factory()->create(['organisation_id' => $this->organisation->id])->id,
            'cost_record_id' => $this->first->id,
            'fiscal_year_id' => $this->open->id,
        ]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id]);
    }

    public function test_every_row_follows_its_own_rules_and_the_result_counts_both(): void
    {
        $this->bulkDelete([$this->first, $this->second, $this->blocked])->assertSessionHasNoErrors();

        $this->assertSame(2, session('bulk')['done']);
        $this->assertSame([['name' => 'Drie', 'reason' => self::FINALISED_REFUSAL]], session('bulk')['skipped']);
        $this->assertSame(['Drie'], CostRecord::query()->pluck('name')->all());
        $this->assertSame('2 kostenposten verwijderd', session('undo')['message']);
    }

    public function test_one_undo_restores_every_row_the_bulk_deletion_removed(): void
    {
        $figures = YearlyFigure::query()->orderBy('id')->pluck('id')->all();
        $sets = AllocationSet::query()->orderBy('id')->pluck('id')->all();
        $lines = AllocationLine::query()->orderBy('id')->pluck('id')->all();

        $this->bulkDelete([$this->first, $this->second, $this->blocked])->assertSessionHasNoErrors();
        $token = (string) session('undo')['token'];

        $this->admin()->post('/undo/'.$token)->assertSessionHasNoErrors();

        $this->assertSame(['Drie', 'Een', 'Twee'], CostRecord::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame($figures, YearlyFigure::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($sets, AllocationSet::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($lines, AllocationLine::query()->orderBy('id')->pluck('id')->all());
        $this->assertNull(UndoRecord::find($token));
    }

    public function test_a_selection_of_blocked_rows_only_deletes_nothing_and_offers_no_undo(): void
    {
        $this->bulkDelete([$this->blocked])->assertSessionHasNoErrors();

        $this->assertSame(0, session('bulk')['done']);
        $this->assertSame(3, CostRecord::query()->count());
        $this->assertNull(session('undo'));
    }

    public function test_a_cost_record_of_another_organisation_is_left_alone(): void
    {
        $other = Organisation::factory()->create();
        $outsider = CostRecord::factory()->create([
            'organisation_id' => $other->id,
            'owner_id' => Owner::factory()->create(['organisation_id' => $other->id])->id,
        ]);

        $this->bulkDelete([$this->second, $outsider])->assertSessionHasNoErrors();

        $this->assertSame(1, session('bulk')['done']);
        $this->assertNotNull($outsider->fresh());
    }

    public function test_an_archived_organisation_refuses_the_whole_selection(): void
    {
        $this->organisation->update(['archived_at' => now()]);

        $this->bulkDelete([$this->first, $this->second])->assertSessionHasErrors(['rule' => 'Deze organisatie is gearchiveerd']);

        $this->assertSame(3, CostRecord::query()->count());
    }

    public function test_a_reader_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post("/organisations/{$this->organisation->id}/cost-records/bulk-delete", ['ids' => [$this->first->id]])
            ->assertForbidden();

        $this->assertSame(3, CostRecord::query()->count());
    }

    private function record(string $name): CostRecord
    {
        return CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'owner_id' => $this->owner->id, 'name' => $name]);
    }

    /**
     * @param  list<CostRecord>  $records
     * @return TestResponse<Response>
     */
    private function bulkDelete(array $records): TestResponse
    {
        return $this->admin()->post("/organisations/{$this->organisation->id}/cost-records/bulk-delete", [
            'ids' => array_map(fn (CostRecord $record) => $record->id, $records),
        ]);
    }

    private function admin(): static
    {
        return $this->actingAs($this->administrator ??= User::factory()->administrator()->create());
    }
}
