<?php

namespace Tests\Unit\Calculation;

use App\Calculation\CalculationInput;
use App\Calculation\FigureCalculator;
use App\Calculation\Figures;
use PHPUnit\Framework\TestCase;

class EdgeCasesTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, list<array{elementId: string, basisPoints: int}>>  $sets
     */
    private function calculate(array $records, array $sets = []): Figures
    {
        $elements = [
            ['id' => 'd1', 'parentId' => null, 'areaKey' => 'primary_activities', 'kind' => 'domain', 'modalityKey' => null, 'isUnknownAllocation' => false],
            ['id' => 'a1', 'parentId' => 'd1', 'areaKey' => 'primary_activities', 'kind' => 'activity', 'modalityKey' => null, 'isUnknownAllocation' => false],
            ['id' => 'a2', 'parentId' => 'd1', 'areaKey' => 'primary_activities', 'kind' => 'activity', 'modalityKey' => null, 'isUnknownAllocation' => false],
            ['id' => 'u', 'parentId' => null, 'areaKey' => 'supporting_activities', 'kind' => 'domain', 'modalityKey' => null, 'isUnknownAllocation' => true],
        ];
        $areas = [['key' => 'primary_activities', 'position' => 7, 'carriesCosts' => true], ['key' => 'supporting_activities', 'position' => 8, 'carriesCosts' => true]];
        $records = array_map(fn (array $record) => $record + ['costKind' => 'personnel', 'ownerId' => null, 'applicationId' => null, 'forecastCents' => null, 'runBp' => null, 'changeBp' => null, 'fteHundredths' => null], $records);

        return (new FigureCalculator)->calculate(new CalculationInput($elements, $areas, $records, $sets));
    }

    public function test_a_canvas_total_beyond_the_double_scaled_integer_range_still_gives_shares(): void
    {
        $records = [];
        $sets = [];
        for ($i = 0; $i < 5000; $i++) {
            $records[] = ['id' => "r$i", 'budgetCents' => 99_999_999_999];
            $sets["r$i"] = [['elementId' => 'a1', 'basisPoints' => 10000]];
        }

        $figures = $this->calculate($records, $sets);

        $this->assertSame(10000, $figures->elements['a1']['shareBp']);
        $this->assertTrue($figures->closingCheck['passed']);
    }

    public function test_zero_budget_with_a_set_gives_zero_amounts_and_passes(): void
    {
        $figures = $this->calculate([['id' => 'r', 'budgetCents' => 0]], ['r' => [['elementId' => 'a1', 'basisPoints' => 4000], ['elementId' => 'a2', 'basisPoints' => 6000]]]);

        $this->assertSame(0, $figures->elements['a1']['direct']['budget']);
        $this->assertTrue($figures->closingCheck['passed']);
    }

    public function test_an_organisation_without_cost_records_is_all_zero_with_null_shares(): void
    {
        $figures = $this->calculate([]);

        $this->assertSame(array_fill_keys(['budget', 'forecast', 'run', 'change', 'undetermined'], 0), $figures->canvasTotal);
        $this->assertTrue($figures->closingCheck['passed']);
        foreach ($figures->elements as $element) {
            $this->assertNull($element['shareBp']);
        }
    }

    public function test_maximum_budget_at_one_basis_point_is_exact(): void
    {
        $figures = $this->calculate([['id' => 'r', 'budgetCents' => 99999999999]], ['r' => [['elementId' => 'a1', 'basisPoints' => 1], ['elementId' => 'a2', 'basisPoints' => 9999]]]);

        $this->assertSame(10000000, $figures->elements['a1']['direct']['budget']);
        $this->assertSame(99989999999, $figures->elements['a2']['direct']['budget']);
        $this->assertTrue($figures->closingCheck['passed']);
    }

    public function test_a_cost_record_without_forecast_counts_its_budget(): void
    {
        $figures = $this->calculate([['id' => 'r', 'budgetCents' => 12345]]);

        $this->assertSame(12345, $figures->canvasTotal['forecast']);
        $this->assertTrue($figures->closingCheck['passed']);
    }

    public function test_archived_cost_records_present_in_the_input_are_included(): void
    {
        $figures = $this->calculate([['id' => 'r', 'budgetCents' => 500, 'archivedAt' => '2026-01-01']], ['r' => [['elementId' => 'a1', 'basisPoints' => 10000]]]);

        $this->assertSame(500, $figures->elements['a1']['direct']['budget']);
        $this->assertSame(500, $figures->canvasTotal['budget']);
    }
}
