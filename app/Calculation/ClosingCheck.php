<?php

namespace App\Calculation;

final class ClosingCheck
{
    /** @return array{passed: bool, failures: list<array{check: string, differenceCents: int}>} */
    public static function run(CalculationInput $input, Figures $figures): array
    {
        $failures = [];
        $expect = function (string $check, int $actual, int $expected) use (&$failures): void {
            if ($actual !== $expected) {
                $failures[] = ['check' => $check, 'differenceCents' => $actual - $expected];
            }
        };

        $budgets = array_sum(array_column($input->costRecords, 'budgetCents'));
        $forecasts = array_sum(array_map(fn (array $record) => $record['forecastCents'] ?? $record['budgetCents'], $input->costRecords));
        $expect('budget', array_sum(array_map(fn (array $element) => $element['direct']['budget'], $figures->elements)), $budgets);
        $expect('forecast', array_sum(array_map(fn (array $element) => $element['direct']['forecast'], $figures->elements)), $forecasts);

        $children = [];
        $roots = [];
        foreach ($input->elements as $element) {
            if ($element['parentId'] === null) {
                $roots[$element['areaKey']][] = $element['id'];
            } else {
                $children[$element['parentId']][] = $element['id'];
            }
        }
        $areaKeys = array_unique([...array_keys($figures->areas), ...array_keys($roots)]);

        foreach (Measures::NAMES as $measure) {
            foreach ($figures->elements as $id => $element) {
                $sum = $element['direct'][$measure];
                foreach ($children[$id] ?? [] as $child) {
                    $sum += $figures->elements[$child]['total'][$measure];
                }
                $expect("total.$measure.$id", $element['total'][$measure], $sum);
            }

            $canvas = 0;
            foreach ($areaKeys as $key) {
                $sum = array_sum(array_map(fn (string|int $id) => $figures->elements[$id]['total'][$measure], $roots[$key] ?? []));
                $expect("area.$key.$measure", $figures->areas[$key][$measure] ?? 0, $sum);
                $canvas += $figures->areas[$key][$measure] ?? 0;
            }
            $expect("canvas.$measure", $figures->canvasTotal[$measure], $canvas);

            $expect("breakdown.costKind.$measure", array_sum(array_column($figures->breakdowns['costKind'], $measure)), $figures->canvasTotal[$measure]);
            $expect("breakdown.modality.$measure", array_sum(array_column($figures->breakdowns['modality'], $measure)), $figures->canvasTotal[$measure]);
        }
        $expect('breakdown.split', array_sum($figures->breakdowns['split']), $figures->canvasTotal['budget']);

        return ['passed' => $failures === [], 'failures' => $failures];
    }
}
