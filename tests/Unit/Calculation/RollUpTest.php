<?php

namespace Tests\Unit\Calculation;

use App\Calculation\CalculationInput;
use App\Calculation\FigureCalculator;
use App\Calculation\Figures;
use PHPUnit\Framework\TestCase;

class RollUpTest extends TestCase
{
    /**
     * @param  array<string, list<array{elementId: string, basisPoints: int}>>  $sets
     * @param  list<array<string, mixed>>  $records
     */
    private function calculate(array $sets, array $records): Figures
    {
        $row = fn (string $id, ?string $parent, string $area, string $kind, bool $unknown = false) => [
            'id' => $id, 'parentId' => $parent, 'areaKey' => $area, 'kind' => $kind, 'modalityKey' => null, 'isUnknownAllocation' => $unknown,
        ];
        $elements = [
            $row('d1', null, 'primary_activities', 'domain'),
            $row('a1', 'd1', 'primary_activities', 'activity'),
            $row('cl', 'd1', 'primary_activities', 'cluster'),
            $row('a2', 'cl', 'primary_activities', 'activity'),
            $row('a3', 'cl', 'primary_activities', 'activity'),
            $row('u', null, 'supporting_activities', 'domain', true),
        ];
        $areas = [
            ['key' => 'primary_activities', 'position' => 7, 'carriesCosts' => true],
            ['key' => 'supporting_activities', 'position' => 8, 'carriesCosts' => true],
        ];
        $costRecords = array_map(fn (array $record) => $record + ['costKind' => 'personnel', 'ownerId' => null, 'applicationId' => null, 'forecastCents' => null, 'runBp' => null, 'changeBp' => null, 'fteHundredths' => null], $records);

        return (new FigureCalculator)->calculate(new CalculationInput($elements, $areas, $costRecords, $sets));
    }

    public function test_direct_and_total_with_mixed_levels(): void
    {
        $figures = $this->calculate(
            ['r1' => [['elementId' => 'd1', 'basisPoints' => 5000], ['elementId' => 'a2', 'basisPoints' => 5000]]],
            [['id' => 'r1', 'budgetCents' => 1001]],
        );

        $this->assertSame(501, $figures->elements['d1']['direct']['budget']);
        $this->assertSame(500, $figures->elements['a2']['direct']['budget']);
        $this->assertSame(500, $figures->elements['cl']['total']['budget']);
        $this->assertSame(0, $figures->elements['cl']['direct']['budget']);
        $this->assertSame(1001, $figures->elements['d1']['total']['budget']);
        $this->assertSame(1001, $figures->areas['primary_activities']['budget']);
    }

    public function test_unknown_allocation_counts_in_supporting_activities_and_canvas_total(): void
    {
        $figures = $this->calculate(
            ['r1' => [['elementId' => 'a1', 'basisPoints' => 10000]]],
            [['id' => 'r1', 'budgetCents' => 700], ['id' => 'r2', 'budgetCents' => 300, 'forecastCents' => 400]],
        );

        $this->assertSame(300, $figures->areas['supporting_activities']['budget']);
        $this->assertSame(400, $figures->areas['supporting_activities']['forecast']);
        $this->assertSame(1000, $figures->canvasTotal['budget']);
        $this->assertSame(1100, $figures->canvasTotal['forecast']);
    }

    public function test_share_is_basis_points_of_the_canvas_total_rounded_half_up(): void
    {
        $figures = $this->calculate(
            ['r1' => [['elementId' => 'a1', 'basisPoints' => 10000]]],
            [['id' => 'r1', 'budgetCents' => 1], ['id' => 'r2', 'budgetCents' => 2]],
        );

        $this->assertSame(3333, $figures->elements['a1']['shareBp']);
        $this->assertSame(6667, $figures->elements['u']['shareBp']);
        $this->assertSame(10000, $figures->elements['d1']['shareBp'] + $figures->elements['u']['shareBp']);
    }

    public function test_share_is_null_when_the_canvas_total_is_zero(): void
    {
        $figures = $this->calculate([], []);

        $this->assertSame(0, $figures->canvasTotal['budget']);
        $this->assertNull($figures->elements['d1']['shareBp']);
    }
}
