<?php

namespace Tests\Unit\Calculation;

use App\Calculation\CalculationInput;
use App\Calculation\ClosingCheck;
use App\Calculation\FigureCalculator;
use App\Calculation\Figures;
use PHPUnit\Framework\TestCase;

class ClosingCheckTest extends TestCase
{
    /** @param array<string, list<array{elementId: string, basisPoints: int}>> $sets */
    private function input(array $sets, bool $dropElement = false): CalculationInput
    {
        $row = fn (string $id, ?string $parent, bool $unknown = false) => [
            'id' => $id, 'parentId' => $parent, 'areaKey' => $unknown ? 'supporting_activities' : 'primary_activities', 'kind' => $parent ? 'activity' : 'domain', 'modalityKey' => null, 'isUnknownAllocation' => $unknown,
        ];
        $elements = array_values(array_filter([$row('d1', null), $dropElement ? null : $row('a1', 'd1'), $row('u', null, true)]));
        $areas = [['key' => 'primary_activities', 'position' => 7, 'carriesCosts' => true], ['key' => 'supporting_activities', 'position' => 8, 'carriesCosts' => true]];
        $records = [
            ['id' => 'r1', 'costKind' => 'personnel', 'ownerId' => null, 'applicationId' => null, 'budgetCents' => 10001, 'forecastCents' => 9000, 'runBp' => 4000, 'changeBp' => 3000, 'fteHundredths' => 100],
            ['id' => 'r2', 'costKind' => 'licences', 'ownerId' => null, 'applicationId' => null, 'budgetCents' => 777, 'forecastCents' => null, 'runBp' => null, 'changeBp' => null, 'fteHundredths' => null],
        ];

        return new CalculationInput($elements, $areas, $records, $sets);
    }

    private function figures(): Figures
    {
        return (new FigureCalculator)->calculate($this->input(['r1' => [['elementId' => 'a1', 'basisPoints' => 3333], ['elementId' => 'd1', 'basisPoints' => 6667]]]));
    }

    /** @param array<string, int> $canvasTotal */
    private function withCanvasTotal(Figures $figures, array $canvasTotal): Figures
    {
        return new Figures($figures->elements, $figures->lines, $figures->areas, $canvasTotal, $figures->breakdowns, $figures->sidePanel);
    }

    public function test_a_consistent_calculation_passes(): void
    {
        $this->assertSame(['passed' => true, 'failures' => []], $this->figures()->closingCheck);
    }

    public function test_an_injected_one_cent_discrepancy_fails_and_names_the_check(): void
    {
        $figures = $this->figures();
        $tampered = $this->withCanvasTotal($figures, ['budget' => $figures->canvasTotal['budget'] + 1] + $figures->canvasTotal);

        $result = ClosingCheck::run($this->input([]), $tampered);

        $this->assertFalse($result['passed']);
        $this->assertContains(['check' => 'canvas.budget', 'differenceCents' => 1], $result['failures']);
    }

    public function test_a_discrepancy_in_a_total_fails(): void
    {
        $figures = $this->figures();
        $elements = $figures->elements;
        $elements['d1']['total']['forecast']--;
        $tampered = new Figures($elements, $figures->lines, $figures->areas, $figures->canvasTotal, $figures->breakdowns, $figures->sidePanel);

        $result = ClosingCheck::run($this->input([]), $tampered);

        $this->assertFalse($result['passed']);
        $this->assertSame('total.forecast.d1', $result['failures'][0]['check']);
        $this->assertSame(-1, $result['failures'][0]['differenceCents']);
    }

    public function test_a_discrepancy_in_a_breakdown_fails(): void
    {
        $figures = $this->figures();
        $breakdowns = $figures->breakdowns;
        $breakdowns['costKind']['personnel']['budget'] += 1;
        $breakdowns['split']['run'] -= 1;
        $tampered = new Figures($figures->elements, $figures->lines, $figures->areas, $figures->canvasTotal, $breakdowns, $figures->sidePanel);

        $checks = array_column(ClosingCheck::run($this->input([]), $tampered)['failures'], 'check');

        $this->assertSame(['breakdown.costKind.budget', 'breakdown.split'], $checks);
    }

    public function test_a_line_on_an_element_missing_from_the_input_fails_the_budget_check(): void
    {
        $figures = (new FigureCalculator)->calculate($this->input(['r1' => [['elementId' => 'a1', 'basisPoints' => 3333], ['elementId' => 'd1', 'basisPoints' => 6667]]], true));

        $this->assertFalse($figures->closingCheck['passed']);
        $this->assertContains(['check' => 'budget', 'differenceCents' => -3333], $figures->closingCheck['failures']);
    }
}
