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
            $elements[$element['id']] = ['direct' => Measures::zero()];
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

        return new Figures($elements, $lines);
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
