<?php

namespace Tests\Feature\Structure;

use App\Exceptions\RedistributionRequired;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Structure\ElementArchive;
use App\Services\Structure\ElementDeletion;
use Closure;

class ElementAllocationGuardTest extends StructureTestCase
{
    private User $administrator;

    private FiscalYear $first;

    private FiscalYear $second;

    private CostRecord $record;

    private CostRecord $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();

        $organisation = $this->canvas->organisation;

        $this->first = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2025]);
        $this->second = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);
        $this->record = CostRecord::factory()->create(['organisation_id' => $organisation->id]);
        $this->other = CostRecord::factory()->create(['organisation_id' => $organisation->id]);
    }

    private function deletion(): ElementDeletion
    {
        return app(ElementDeletion::class);
    }

    private function archive(): ElementArchive
    {
        return app(ElementArchive::class);
    }

    private function line(Element $target, FiscalYear $year, CostRecord $record, int $points = 10_000): AllocationLine
    {
        $set = AllocationSet::firstOrCreate([
            'canvas_id' => $this->canvas->id,
            'cost_record_id' => $record->id,
            'fiscal_year_id' => $year->id,
        ]);

        return AllocationLine::factory()->create([
            'allocation_set_id' => $set->id,
            'element_id' => $target->id,
            'basis_points' => $points,
            'position' => $set->lines()->count(),
        ]);
    }

    private function refused(Closure $write): RedistributionRequired
    {
        try {
            $write();
        } catch (RedistributionRequired $refusal) {
            return $refusal;
        }

        $this->fail('The write was not refused');
    }

    /** @return list<array{string, int}> */
    private function stored(): array
    {
        return AllocationLine::query()->orderBy('id')->get()
            ->map(fn (AllocationLine $line) => [(string) $line->element_id, $line->basis_points])->all();
    }

    public function test_deleting_an_element_whose_subtree_carries_lines_is_refused_with_the_affected_years(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain);
        $this->line($activity, $this->first, $this->record);
        $this->line($activity, $this->second, $this->record, 6_000);
        $this->line($activity, $this->second, $this->other);
        $lines = $this->stored();

        $refusal = $this->refused(fn () => $this->deletion()->delete($domain, $this->administrator));

        $this->assertSame('Op dit element staan nog kosten. Herverdeel ze eerst', (string) $refusal->errors()['rule'][0]);
        $this->assertSame([
            ['fiscal_year_id' => $this->first->id, 'year' => 2025, 'cost_records' => 1, 'basis_points' => 10_000],
            ['fiscal_year_id' => $this->second->id, 'year' => 2026, 'cost_records' => 2, 'basis_points' => 16_000],
        ], $refusal->years);
        $this->assertSame(2, Element::count());
        $this->assertSame($lines, $this->stored());
    }

    public function test_archiving_an_element_whose_subtree_carries_lines_is_refused(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);
        $this->line($this->activity($cluster), $this->second, $this->record);

        $refusal = $this->refused(fn () => $this->archive()->archive($domain));

        $this->assertSame([['fiscal_year_id' => $this->second->id, 'year' => 2026, 'cost_records' => 1, 'basis_points' => 10_000]], $refusal->years);
        $this->assertNull($domain->fresh()?->archived_at);
    }

    public function test_an_element_without_lines_is_deleted_and_archived_and_an_archived_target_holds_no_lines(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain);
        $this->line($activity, $this->second, $this->record);
        $spare = $this->domain('supporting_activities', 'Facilitair');

        $this->deletion()->delete($spare, $this->administrator);
        $this->assertSame(2, Element::count());

        AllocationLine::query()->delete();
        $this->archive()->archive($domain);

        $this->assertNotNull($domain->fresh()?->archived_at);
        $this->assertNotNull($activity->fresh()?->archived_at);
        $this->assertSame(0, AllocationLine::query()->whereIn('element_id', [$domain->id, $activity->id])->count());
    }

    public function test_lifting_a_cluster_keeps_the_lines_of_its_activities_and_only_its_own_lines_are_refused(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);
        $activity = $this->activity($cluster);
        $this->line($activity, $this->second, $this->record);
        $lines = $this->stored();

        $this->deletion()->delete($cluster, $this->administrator, 'lift');

        $this->assertSame($domain->id, $activity->fresh()?->parent_id);
        $this->assertSame($lines, $this->stored());

        $second = $this->cluster($domain, 'Contracten');
        $this->line($second, $this->second, $this->other);

        $this->refused(fn () => $this->deletion()->delete($second, $this->administrator, 'lift'));

        $this->assertNotNull($second->fresh());
    }

    public function test_moving_a_domain_keeps_its_lines_on_the_same_identifiers(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain);
        $this->line($activity, $this->second, $this->record, 4_000);
        $lines = $this->stored();

        $this->tree()->move($domain, $this->area('supporting_activities'));

        $this->assertSame($this->area('supporting_activities')->id, $activity->fresh()?->area_id);
        $this->assertSame($lines, $this->stored());
    }
}
