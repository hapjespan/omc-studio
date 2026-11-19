<?php

namespace Tests\Unit\Calculation;

use App\Calculation\CalculationInput;
use App\Calculation\FigureCalculator;
use App\Calculation\Figures;
use PHPUnit\Framework\TestCase;

class LineAmountsTest extends TestCase
{
    /**
     * @param  array<string, list<array{elementId: string, basisPoints: int}>>  $sets
     * @param  array<string, mixed>  $record
     */
    private function calculate(array $sets, array $record = []): Figures
    {
        $elements = [];
        foreach (['a', 'b', 'c'] as $id) {
            $elements[] = ['id' => $id, 'parentId' => null, 'areaKey' => 'primary_activities', 'kind' => 'domain', 'modalityKey' => null, 'isUnknownAllocation' => false];
        }
        $elements[] = ['id' => 'u', 'parentId' => null, 'areaKey' => 'supporting_activities', 'kind' => 'domain', 'modalityKey' => null, 'isUnknownAllocation' => true];

        $costRecord = $record + ['id' => 'r', 'costKind' => 'personnel', 'ownerId' => null, 'applicationId' => null, 'budgetCents' => 23000, 'forecastCents' => null, 'runBp' => null, 'changeBp' => null, 'fteHundredths' => null];

        return (new FigureCalculator)->calculate(new CalculationInput($elements, [], [$costRecord], $sets));
    }

    /** @return list<array{elementId: string, basisPoints: int}> */
    private function set(string $ids, int ...$bp): array
    {
        $lines = [];
        foreach (str_split($ids) as $index => $id) {
            $lines[] = ['elementId' => $id, 'basisPoints' => $bp[$index]];
        }

        return $lines;
    }

    public function test_budget_is_split_by_largest_remainder(): void
    {
        $figures = $this->calculate(['r' => $this->set('abc', 3333, 3333, 3334)]);

        $this->assertSame([7666, 7666, 7668], array_map(fn (array $line) => $line['amounts']['budget'], $figures->lines['r']));
        $this->assertSame(7668, $figures->elements['c']['direct']['budget']);
    }

    public function test_missing_forecast_equals_budget_and_forecast_is_split_alike(): void
    {
        $figures = $this->calculate(['r' => $this->set('abc', 3333, 3333, 3334)]);
        $this->assertSame(7666, $figures->elements['a']['direct']['forecast']);

        $figures = $this->calculate(['r' => $this->set('ab', 5000, 5000)], ['forecastCents' => 101]);
        $this->assertSame([51, 50], array_map(fn (array $line) => $line['amounts']['forecast'], $figures->lines['r']));
    }

    public function test_cost_record_without_set_is_one_line_on_unknown_allocation(): void
    {
        $figures = $this->calculate([]);

        $this->assertSame(23000, $figures->elements['u']['direct']['budget']);
        $this->assertSame(23000, $figures->elements['u']['direct']['forecast']);
        $this->assertSame(10000, $figures->lines['r'][0]['basisPoints']);
    }

    public function test_run_change_undetermined_split_per_line(): void
    {
        $figures = $this->calculate(['r' => $this->set('ab', 5000, 5000)], ['budgetCents' => 100000, 'runBp' => 6000, 'changeBp' => 3000]);

        foreach (['a', 'b'] as $id) {
            $this->assertSame(30000, $figures->elements[$id]['direct']['run']);
            $this->assertSame(15000, $figures->elements[$id]['direct']['change']);
            $this->assertSame(5000, $figures->elements[$id]['direct']['undetermined']);
        }
    }

    public function test_run_change_undetermined_ties_go_in_that_order(): void
    {
        $figures = $this->calculate(['r' => $this->set('a', 10000)], ['budgetCents' => 1, 'runBp' => 3333, 'changeBp' => 3333]);

        $this->assertSame(['run' => 0, 'change' => 0, 'undetermined' => 1], array_intersect_key($figures->elements['a']['direct'], ['run' => 0, 'change' => 0, 'undetermined' => 0]));

        $figures = $this->calculate(['r' => $this->set('a', 10000)], ['budgetCents' => 1, 'runBp' => 5000, 'changeBp' => 5000]);
        $this->assertSame(1, $figures->elements['a']['direct']['run']);
        $this->assertSame(0, $figures->elements['a']['direct']['change']);
    }

    public function test_one_cent_over_three_targets_goes_to_the_first_in_set_order(): void
    {
        $figures = $this->calculate(['r' => $this->set('cba', 3333, 3333, 3334)], ['budgetCents' => 1]);

        $this->assertSame(1, $figures->elements['a']['direct']['budget']);
        $this->assertSame(0, $figures->elements['c']['direct']['budget']);
    }

    public function test_one_hundred_percent_on_one_target_equals_the_budget(): void
    {
        $figures = $this->calculate(['r' => $this->set('b', 10000)], ['budgetCents' => 99999999999]);

        $this->assertSame(99999999999, $figures->elements['b']['direct']['budget']);
    }
}
