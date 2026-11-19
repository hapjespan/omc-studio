<?php

namespace App\Calculation;

/**
 * @phpstan-import-type Money from Figures
 * @phpstan-import-type LineRow from Figures
 */
final class FigureCalculator
{
    public function calculate(CalculationInput $input): Figures
    {
        $unknownId = null;
        $elements = [];
        foreach ($input->elements as $element) {
            $elements[$element['id']] = ['direct' => Measures::zero(), 'total' => Measures::zero(), 'shareBp' => null];
            if ($element['isUnknownAllocation']) {
                $unknownId = $element['id'];
            }
        }

        $lines = [];
        foreach ($input->costRecords as $record) {
            $lines[$record['id']] = $this->lines($record, $input->sets[$record['id']] ?? [], $unknownId);
            foreach ($lines[$record['id']] as $line) {
                if (isset($elements[$line['elementId']])) {
                    $elements[$line['elementId']]['direct'] = Measures::add($elements[$line['elementId']]['direct'], $line['amounts']);
                }
            }
        }

        $children = [];
        foreach ($input->elements as $element) {
            $children[$element['parentId'] ?? ''][] = $element['id'];
        }
        $areas = [];
        foreach ($input->areas as $area) {
            $areas[$area['key']] = Measures::zero();
        }
        $canvasTotal = Measures::zero();
        $totals = [];
        foreach ($input->elements as $element) {
            if ($element['parentId'] === null) {
                $total = $this->rollUp($element['id'], $children, $elements, $totals);
                $areas[$element['areaKey']] = Measures::add($areas[$element['areaKey']] ?? Measures::zero(), $total);
            }
        }
        foreach ($totals as $id => $total) {
            $elements[$id]['total'] = $total;
        }
        foreach ($areas as $area) {
            $canvasTotal = Measures::add($canvasTotal, $area);
        }
        foreach ($elements as &$figures) {
            $figures['shareBp'] = $canvasTotal['budget'] === 0
                ? null
                : intdiv(2 * $figures['total']['budget'] * 10000 + $canvasTotal['budget'], 2 * $canvasTotal['budget']);
        }
        unset($figures);

        return new Figures($elements, $lines, $areas, $canvasTotal);
    }

    /**
     * @param  array<string|int, list<string|int>>  $children
     * @param  array<string|int, array{direct: array<string, int>, total: array<string, int>, shareBp: int|null}>  $elements
     * @param  array<string|int, array<string, int>>  $totals
     * @return array<string, int>
     */
    private function rollUp(string|int $id, array $children, array $elements, array &$totals): array
    {
        $total = $elements[$id]['direct'];
        foreach ($children[$id] ?? [] as $child) {
            $total = Measures::add($total, $this->rollUp($child, $children, $elements, $totals));
        }

        return $totals[$id] = $total;
    }

    /**
     * @param  array{id: string|int, budgetCents: int, forecastCents: int|null, runBp: int|null, changeBp: int|null}  $record
     * @param  list<array{elementId: string|int, basisPoints: int}>  $set
     * @return list<LineRow>
     */
    private function lines(array $record, array $set, string|int|null $unknownId): array
    {
        if ($set === []) {
            $set = [['elementId' => $unknownId ?? '', 'basisPoints' => 10000]];
        }

        $weights = array_column($set, 'basisPoints');
        $budget = LargestRemainder::split($record['budgetCents'], $weights);
        $forecast = LargestRemainder::split($record['forecastCents'] ?? $record['budgetCents'], $weights);
        $run = $record['runBp'] ?? 0;
        $change = $record['changeBp'] ?? 0;
        $split = [$run, $change, 10000 - $run - $change];

        $lines = [];
        foreach ($set as $index => $line) {
            [$runCents, $changeCents, $undeterminedCents] = LargestRemainder::split($budget[$index], $split);
            $lines[] = [
                'elementId' => $line['elementId'],
                'basisPoints' => $line['basisPoints'],
                'amounts' => [
                    'budget' => $budget[$index],
                    'forecast' => $forecast[$index],
                    'run' => $runCents,
                    'change' => $changeCents,
                    'undetermined' => $undeterminedCents,
                ],
            ];
        }

        return $lines;
    }
}
