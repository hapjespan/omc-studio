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
            $elements[$element['id']] = ['direct' => Measures::zero(), 'total' => Measures::zero(), 'shareBp' => null, 'fteNumerator' => 0, 'contributions' => []];
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
            $figures['shareBp'] = $canvasTotal['budget'] === 0 ? null : $this->shareBp($figures['total']['budget'], $canvasTotal['budget']);
        }
        unset($figures);

        $parents = array_column($input->elements, 'parentId', 'id');
        foreach ($input->costRecords as $record) {
            foreach ($lines[$record['id']] as $line) {
                for ($id = $line['elementId']; $id !== null && isset($elements[$id]); $id = $parents[$id]) {
                    $elements[$id]['fteNumerator'] += ($record['fteHundredths'] ?? 0) * $line['basisPoints'];
                    $previous = $elements[$id]['contributions'][$record['id']] ?? ['amountCents' => 0, 'basisPoints' => 0];
                    $elements[$id]['contributions'][$record['id']] = [
                        'amountCents' => $previous['amountCents'] + $line['amounts']['budget'],
                        'basisPoints' => $previous['basisPoints'] + $line['basisPoints'],
                    ];
                }
            }
        }

        $figures = new Figures($elements, $lines, $areas, $canvasTotal, $this->breakdowns($input, $lines, $elements), $this->sidePanel($input, $elements, $unknownId));

        return new Figures($figures->elements, $figures->lines, $figures->areas, $figures->canvasTotal, $figures->breakdowns, $figures->sidePanel, ClosingCheck::run($input, $figures));
    }

    /**
     * @param  array<string|int, list<LineRow>>  $lines
     * @param  array<string|int, array{direct: Money}>  $elements
     * @return array{costKind: array<string, Money>, modality: array<string, Money>, split: array{run: int, change: int, undetermined: int}}
     */
    private function breakdowns(CalculationInput $input, array $lines, array $elements): array
    {
        $costKind = [];
        $split = ['run' => 0, 'change' => 0, 'undetermined' => 0];
        foreach ($input->costRecords as $record) {
            $costKind[$record['costKind']] ??= Measures::zero();
            foreach ($lines[$record['id']] as $line) {
                $costKind[$record['costKind']] = Measures::add($costKind[$record['costKind']], $line['amounts']);
                foreach ($split as $name => $cents) {
                    $split[$name] = $cents + $line['amounts'][$name];
                }
            }
        }

        $modality = ['none' => Measures::zero(), 'unknown_allocation' => Measures::zero()];
        foreach ($input->elements as $element) {
            $key = $element['isUnknownAllocation'] ? 'unknown_allocation' : (string) ($element['modalityKey'] ?? 'none');
            $modality[$key] = Measures::add($modality[$key] ?? Measures::zero(), $elements[$element['id']]['direct']);
        }

        return ['costKind' => $costKind, 'modality' => $modality, 'split' => $split];
    }

    /**
     * @param  array<string|int, array{direct: Money}>  $elements
     * @return array{allocated: array{budget: int, forecast: int, count: int}, unknown: array{budget: int, forecast: int, count: int}}
     */
    private function sidePanel(CalculationInput $input, array $elements, string|int|null $unknownId): array
    {
        $unknown = $unknownId === null ? Measures::zero() : $elements[$unknownId]['direct'];
        $unknownCount = count(array_filter($input->costRecords, fn (array $record) => ($input->sets[$record['id']] ?? []) === []));
        $budget = array_sum(array_column($input->costRecords, 'budgetCents'));
        $forecast = array_sum(array_map(fn (array $record) => $record['forecastCents'] ?? $record['budgetCents'], $input->costRecords));

        return [
            'allocated' => ['budget' => $budget - $unknown['budget'], 'forecast' => $forecast - $unknown['forecast'], 'count' => count($input->costRecords) - $unknownCount],
            'unknown' => ['budget' => $unknown['budget'], 'forecast' => $unknown['forecast'], 'count' => $unknownCount],
        ];
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

    private function shareBp(int $part, int $whole): int
    {
        $scaled = $part * 10000;

        return intdiv($scaled, $whole) + (2 * ($scaled % $whole) >= $whole ? 1 : 0);
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
