<?php

namespace App\Http\Resources;

use App\Calculation\Figures;

class CanvasFigures
{
    /** @return array<string, mixed> */
    public static function from(Figures $figures): array
    {
        return [
            'elements' => array_map(fn (array $element) => [
                'direct' => $element['direct'],
                'total' => $element['total'],
                'shareBp' => $element['shareBp'],
                'shareTenths' => $element['shareTenths'] ?? ($element['shareBp'] === null ? null : intdiv($element['shareBp'] + 5, 10)),
                'fteHundredths' => intdiv($element['fteNumerator'] + 5000, 10000),
            ], $figures->elements),
            'areas' => $figures->areas,
            'canvasTotal' => $figures->canvasTotal,
            'sidePanel' => $figures->sidePanel,
            'breakdowns' => $figures->breakdowns,
        ];
    }

    /** @return list<array{id: string|int, amountCents: int, basisPoints: int}> */
    public static function contributions(Figures $figures, string $elementId): array
    {
        $rows = [];
        foreach ($figures->elements[$elementId]['contributions'] ?? [] as $id => $row) {
            $rows[] = ['id' => $id, 'amountCents' => $row['amountCents'], 'basisPoints' => min(10000, $row['basisPoints'])];
        }

        return $rows;
    }
}
