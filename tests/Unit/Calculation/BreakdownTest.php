<?php

namespace Tests\Unit\Calculation;

use App\Calculation\CalculationInput;
use App\Calculation\FigureCalculator;
use App\Calculation\Figures;
use PHPUnit\Framework\TestCase;

class BreakdownTest extends TestCase
{
    /**
     * @param  array<string, list<array{elementId: string, basisPoints: int}>>  $sets
     * @param  list<array<string, mixed>>  $records
     */
    private function calculate(array $sets, array $records): Figures
    {
        $row = fn (string $id, ?string $parent, string $kind, ?string $modality, bool $unknown = false) => [
            'id' => $id, 'parentId' => $parent, 'areaKey' => $unknown ? 'supporting_activities' : 'primary_activities', 'kind' => $kind, 'modalityKey' => $modality, 'isUnknownAllocation' => $unknown,
        ];
        $elements = [
            $row('d1', null, 'domain', 'm1'),
            $row('a1', 'd1', 'activity', 'm2'),
            $row('a2', 'd1', 'activity', null),
            $row('u', null, 'domain', null, true),
        ];
        $costRecords = array_map(fn (array $record) => $record + ['costKind' => 'personnel', 'ownerId' => null, 'applicationId' => null, 'forecastCents' => null, 'runBp' => null, 'changeBp' => null, 'fteHundredths' => null], $records);

        return (new FigureCalculator)->calculate(new CalculationInput($elements, [], $costRecords, $sets));
    }

    public function test_modality_breakdown_counts_direct_amounts_once_and_completes_the_total(): void
    {
        $figures = $this->calculate(
            ['r1' => [['elementId' => 'd1', 'basisPoints' => 2500], ['elementId' => 'a1', 'basisPoints' => 2500], ['elementId' => 'a2', 'basisPoints' => 5000]]],
            [['id' => 'r1', 'budgetCents' => 1000], ['id' => 'r2', 'budgetCents' => 400]],
        );
        $modality = $figures->breakdowns['modality'];

        $this->assertSame(250, $modality['m1']['budget']);
        $this->assertSame(250, $modality['m2']['budget']);
        $this->assertSame(500, $modality['none']['budget']);
        $this->assertSame(400, $modality['unknown_allocation']['budget']);
        $this->assertSame($figures->canvasTotal['budget'], array_sum(array_column($modality, 'budget')));
    }

    public function test_cost_kind_breakdown_and_run_change_undetermined(): void
    {
        $figures = $this->calculate([], [
            ['id' => 'r1', 'budgetCents' => 1000, 'costKind' => 'personnel', 'runBp' => 6000, 'changeBp' => 3000],
            ['id' => 'r2', 'budgetCents' => 500, 'costKind' => 'licences'],
        ]);

        $this->assertSame(1000, $figures->breakdowns['costKind']['personnel']['budget']);
        $this->assertSame(500, $figures->breakdowns['costKind']['licences']['budget']);
        $this->assertSame(['run' => 600, 'change' => 300, 'undetermined' => 600], $figures->breakdowns['split']);
    }

    public function test_contributions_per_cost_record_cover_the_subtree(): void
    {
        $figures = $this->calculate(
            ['r1' => [['elementId' => 'a1', 'basisPoints' => 6000], ['elementId' => 'a2', 'basisPoints' => 4000]]],
            [['id' => 'r1', 'budgetCents' => 1000]],
        );

        $this->assertSame(['amountCents' => 600, 'basisPoints' => 6000], $figures->elements['a1']['contributions']['r1']);
        $this->assertSame(['amountCents' => 1000, 'basisPoints' => 10000], $figures->elements['d1']['contributions']['r1']);
        $this->assertSame([], $figures->elements['u']['contributions']);
    }

    public function test_fte_numerator_is_hundredths_times_basis_points_over_the_subtree(): void
    {
        $figures = $this->calculate(
            ['r1' => [['elementId' => 'a1', 'basisPoints' => 6000], ['elementId' => 'a2', 'basisPoints' => 4000]]],
            [['id' => 'r1', 'budgetCents' => 1000, 'fteHundredths' => 250], ['id' => 'r2', 'budgetCents' => 1000, 'fteHundredths' => null]],
        );

        $this->assertSame(250 * 6000, $figures->elements['a1']['fteNumerator']);
        $this->assertSame(250 * 10000, $figures->elements['d1']['fteNumerator']);
        $this->assertSame(0, $figures->elements['u']['fteNumerator']);
    }

    public function test_a_subset_of_cost_kinds_makes_shares_relative_to_the_subset(): void
    {
        $all = [
            ['id' => 'r1', 'budgetCents' => 1000, 'costKind' => 'personnel'],
            ['id' => 'r2', 'budgetCents' => 3000, 'costKind' => 'licences'],
        ];
        $sets = ['r1' => [['elementId' => 'a1', 'basisPoints' => 10000]], 'r2' => [['elementId' => 'a2', 'basisPoints' => 10000]]];

        $this->assertSame(2500, $this->calculate($sets, $all)->elements['a1']['shareBp']);

        $subset = $this->calculate($sets, [$all[0]]);
        $this->assertSame(1000, $subset->canvasTotal['budget']);
        $this->assertSame(10000, $subset->elements['a1']['shareBp']);
    }

    public function test_side_panel_counts_allocated_and_unknown_cost_records(): void
    {
        $figures = $this->calculate(
            ['r1' => [['elementId' => 'a1', 'basisPoints' => 10000]]],
            [['id' => 'r1', 'budgetCents' => 1000], ['id' => 'r2', 'budgetCents' => 400, 'forecastCents' => 500]],
        );

        $this->assertSame(['budget' => 1000, 'forecast' => 1000, 'count' => 1], $figures->sidePanel['allocated']);
        $this->assertSame(['budget' => 400, 'forecast' => 500, 'count' => 1], $figures->sidePanel['unknown']);
    }
}
