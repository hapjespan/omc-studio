<?php

namespace Tests\Feature\Allocation;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\AuditEntry;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Services\Allocation\CarryOver;
use App\Services\Canvas\UnknownAllocationDomain;
use Illuminate\Support\Facades\DB;

class CarryOverTest extends AllocationTestCase
{
    /** @return array{copied: list<array{name: string, set: bool, figures: int}>, skipped: list<array{name: string, reason: string}>, manual: list<array{name: string}>} */
    private function carry(bool $withFigures): array
    {
        return app(CarryOver::class)->run($this->canvas, $this->fiscalYear(2026), $this->fiscalYear(2027), $withFigures);
    }

    private function figure(CostRecord $record, int $year, int $budget = 50_000): YearlyFigure
    {
        return YearlyFigure::factory()->create([
            'cost_record_id' => $record->id,
            'fiscal_year_id' => $this->fiscalYear($year, $record->organisation_id)->id,
            'budget_cents' => $budget,
        ]);
    }

    private function targetSet(CostRecord $record): ?AllocationSet
    {
        return AllocationSet::with('lines')->where('canvas_id', $this->canvas->id)
            ->where('cost_record_id', $record->id)->where('fiscal_year_id', $this->fiscalYear(2027)->id)->first();
    }

    public function test_the_option_copies_yearly_figures_and_the_set_into_an_empty_target_year(): void
    {
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2026, 120_000);
        $this->allocate($record, 2026, [$target->id => 10000]);

        $result = $this->carry(true);

        $copy = YearlyFigure::where('cost_record_id', $record->id)->where('fiscal_year_id', $this->fiscalYear(2027)->id)->firstOrFail();
        $this->assertSame(120_000, $copy->budget_cents);
        $this->assertNotNull($this->targetSet($record));
        $this->assertSame([['name' => 'Licentie', 'set' => true, 'figures' => 1]], $result['copied']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame([], $result['manual']);
    }

    public function test_lines_are_copied_in_stored_order_with_the_same_basis_points_and_positions(): void
    {
        $first = $this->target('Een');
        $second = $this->target('Twee');
        $record = $this->costRecord('Licentie', 2026);
        $this->allocate($record, 2026, [$second->id => 7500, $first->id => 2500]);

        $this->carry(true);

        $lines = $this->targetSet($record)?->lines
            ->map(fn (AllocationLine $line) => [(string) $line->element_id, $line->basis_points, $line->position])->all();

        $this->assertSame([[(string) $second->id, 7500, 0], [(string) $first->id, 2500, 1]], $lines);
    }

    public function test_a_set_without_yearly_figures_in_the_source_year_is_carried_over_too(): void
    {
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', null);
        $this->fiscalYear(2026);
        $this->allocate($record, 2026, [$target->id => 10000]);

        $result = $this->carry(true);

        $this->assertNotNull($this->targetSet($record));
        $this->assertSame([['name' => 'Licentie', 'set' => true, 'figures' => 0]], $result['copied']);
    }

    public function test_without_the_option_only_sets_go_to_cost_records_that_have_yearly_figures_in_the_target_year(): void
    {
        $target = $this->target('Doel');
        $with = $this->costRecord('Met jaarwaarden', 2026);
        $without = $this->costRecord('Zonder jaarwaarden', 2026);
        $this->figure($with, 2027);
        $this->allocate($with, 2026, [$target->id => 10000]);
        $this->allocate($without, 2026, [$target->id => 10000]);

        $result = $this->carry(false);

        $this->assertNotNull($this->targetSet($with));
        $this->assertNull($this->targetSet($without));
        $this->assertSame(0, YearlyFigure::where('cost_record_id', $with->id)->where('fiscal_year_id', $this->fiscalYear(2027)->id)->count() - 1);
        $this->assertSame([['name' => 'Met jaarwaarden', 'set' => true, 'figures' => 0]], $result['copied']);
        $this->assertSame([['name' => 'Zonder jaarwaarden', 'reason' => 'geen jaarwaarden in 2027']], $result['skipped']);
    }

    public function test_a_cost_record_with_a_set_in_the_target_year_is_skipped(): void
    {
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2026);
        $this->figure($record, 2027);
        $this->allocate($record, 2026, [$target->id => 10000]);
        $existing = $this->allocate($record, 2027, [$target->id => 5000]);

        $result = $this->carry(false);

        $this->assertSame(1, AllocationLine::where('allocation_set_id', $existing->id)->count());
        $this->assertSame([['name' => 'Licentie', 'reason' => 'doeljaar niet leeg']], $result['skipped']);
        $this->assertSame([], $result['copied']);
    }

    public function test_with_the_option_yearly_figures_in_the_target_year_skip_the_cost_record_entirely(): void
    {
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2026);
        $this->figure($record, 2027);
        $this->allocate($record, 2026, [$target->id => 10000]);

        $result = $this->carry(true);

        $this->assertNull($this->targetSet($record));
        $this->assertSame([['name' => 'Licentie', 'reason' => 'doeljaar niet leeg']], $result['skipped']);
    }

    public function test_an_archived_cost_record_is_skipped_once_and_nothing_of_it_is_copied(): void
    {
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2026);
        $this->allocate($record, 2026, [$target->id => 10000]);
        $record->update(['archived_at' => now()]);

        $result = $this->carry(true);

        $this->assertNull($this->targetSet($record));
        $this->assertSame(0, YearlyFigure::where('cost_record_id', $record->id)->where('fiscal_year_id', $this->fiscalYear(2027)->id)->count());
        $this->assertSame([], $result['copied']);
        $this->assertSame([['name' => 'Licentie', 'reason' => 'gearchiveerd']], $result['skipped']);
        $this->assertSame([], $result['manual']);
    }

    public function test_an_archived_cost_record_is_skipped_without_the_option_too(): void
    {
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2026);
        $this->figure($record, 2027);
        $this->allocate($record, 2026, [$target->id => 10000]);
        $record->update(['archived_at' => now()]);

        $result = $this->carry(false);

        $this->assertNull($this->targetSet($record));
        $this->assertSame([], $result['copied']);
        $this->assertSame([['name' => 'Licentie', 'reason' => 'gearchiveerd']], $result['skipped']);
    }

    public function test_an_archived_cost_record_with_only_yearly_figures_is_skipped_and_its_figures_stay_uncopied(): void
    {
        $record = $this->costRecord('Licentie', 2026);
        $record->update(['archived_at' => now()]);

        $result = $this->carry(true);

        $this->assertSame(0, YearlyFigure::where('cost_record_id', $record->id)->where('fiscal_year_id', $this->fiscalYear(2027)->id)->count());
        $this->assertSame([], $result['copied']);
        $this->assertSame([['name' => 'Licentie', 'reason' => 'gearchiveerd']], $result['skipped']);
    }

    public function test_every_cost_record_is_counted_once_when_an_archived_one_runs_next_to_a_live_one(): void
    {
        $target = $this->target('Doel');
        $live = $this->costRecord('Actief', 2026, 80_000);
        $archived = $this->costRecord('Oud', 2026, 90_000);
        $this->allocate($live, 2026, [$target->id => 10000]);
        $this->allocate($archived, 2026, [$target->id => 10000]);
        $archived->update(['archived_at' => now()]);

        $result = $this->carry(true);

        $this->assertNotNull($this->targetSet($live));
        $this->assertNull($this->targetSet($archived));
        $this->assertSame([['name' => 'Actief', 'set' => true, 'figures' => 1]], $result['copied']);
        $this->assertSame([['name' => 'Oud', 'reason' => 'gearchiveerd']], $result['skipped']);
        $names = array_merge(array_column($result['copied'], 'name'), array_column($result['skipped'], 'name'), array_column($result['manual'], 'name'));
        $this->assertSame(array_unique($names), $names);
    }

    public function test_a_line_on_an_element_that_is_no_longer_a_target_copies_nothing(): void
    {
        $archived = $this->target('Weg');
        $archived->update(['archived_at' => now()]);
        $unknown = UnknownAllocationDomain::ensure($this->canvas);
        $outside = $this->domain('actors', 'Klanten');

        $first = $this->costRecord('Gearchiveerd doel', 2026);
        $second = $this->costRecord('Onbekende allocatie', 2026);
        $third = $this->costRecord('Buiten de kosten', 2026);
        $this->allocate($first, 2026, [$archived->id => 10000]);
        $this->allocate($second, 2026, [$unknown?->id => 10000]);
        $this->allocate($third, 2026, [$outside->id => 10000]);

        $result = $this->carry(true);

        $this->assertNull($this->targetSet($first));
        $this->assertSame(0, YearlyFigure::where('fiscal_year_id', $this->fiscalYear(2027)->id)->count());
        $this->assertSame([], $result['copied']);
        $this->assertSame(
            [['name' => 'Buiten de kosten'], ['name' => 'Gearchiveerd doel'], ['name' => 'Onbekende allocatie']],
            $result['manual'],
        );
    }

    public function test_the_copies_are_written_under_the_organisation_lock_with_audit_entries(): void
    {
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2026);
        $this->allocate($record, 2026, [$target->id => 10000]);
        AuditEntry::query()->delete();
        $levels = [];
        $outer = DB::transactionLevel();
        AllocationSet::created(function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        $this->actingAs($this->admin());
        $this->carry(true);

        $this->assertSame([$outer + 1], $levels);

        $set = $this->targetSet($record);
        $this->assertSame(1, AuditEntry::where('subject_type', 'allocation_set')->where('subject_id', (string) $set?->id)->count());
        $this->assertSame(1, AuditEntry::where('subject_type', 'yearly_figure')->count());
    }

    public function test_a_cost_record_of_another_organisation_is_left_alone(): void
    {
        $other = $this->costRecord('Vreemd', null, 100_000, Organisation::factory()->create()->id);
        $this->figure($other, 2026);

        $result = $this->carry(true);

        $this->assertSame([], $result['copied']);
        $this->assertSame([], $result['skipped']);
    }
}
