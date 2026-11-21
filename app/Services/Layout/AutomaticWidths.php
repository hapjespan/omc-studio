<?php

namespace App\Services\Layout;

class AutomaticWidths
{
    private const AREA_GAP = 12;

    private const AREA_PADDING = 12;

    private const LIST_COLUMN = 240;

    private const CARD_MIN_WIDTH = 260;

    private const CARD_GAP = 12;

    private const CLUSTER_INDENT = 8;

    private const ITEM_INDENT = 8;

    private const LIST_AREAS = ['actors', 'channels', 'value_propositions'];

    private const PADDING_X = ['domain' => 12, 'cluster' => 8, 'activity' => 8];

    /**
     * @param  list<array{id: string, area: string, parent: ?string, kind: string}>  $elements
     * @param  array<string, int>  $areaWidths
     * @param  array<string, int>  $elementWidths
     * @return array<string, int>
     */
    public static function compute(array $elements, array $areaWidths = [], array $elementWidths = []): array
    {
        $byId = array_column($elements, null, 'id');
        $memo = [];

        $widthOf = function (array $element) use (&$widthOf, &$memo, $byId, $areaWidths, $elementWidths): int {
            if (isset($memo[$element['id']])) {
                return $memo[$element['id']];
            }

            if (isset($elementWidths[$element['id']])) {
                $width = $elementWidths[$element['id']];
            } elseif ($element['parent'] === null) {
                $inner = ($areaWidths[$element['area']] ?? self::areaWidth($element['area'])) - 2 * self::AREA_PADDING;
                $columns = in_array($element['area'], self::LIST_AREAS, true) ? 1 : max(1, intdiv($inner + self::CARD_GAP, self::CARD_MIN_WIDTH + self::CARD_GAP));
                $width = intdiv($inner - ($columns - 1) * self::CARD_GAP, $columns);
            } else {
                $parent = $byId[$element['parent']];
                $parentWidth = $widthOf($parent);
                $width = in_array($element['area'], self::LIST_AREAS, true)
                    ? $parentWidth - self::ITEM_INDENT
                    : $parentWidth - 2 * self::PADDING_X[$parent['kind']] - ($element['kind'] === 'cluster' ? self::CLUSTER_INDENT : 0);
            }

            return $memo[$element['id']] = $width;
        };

        $widths = [];
        foreach ($elements as $element) {
            $widths[$element['id']] = $widthOf($element);
        }

        return $widths;
    }

    public static function areaWidth(string $key): int
    {
        return match (true) {
            $key === 'direction' => Artboard::WIDTH,
            $key === 'steering' || $key === 'governing' => intdiv(Artboard::WIDTH - 3 * self::AREA_GAP, 2),
            in_array($key, self::LIST_AREAS, true) => self::LIST_COLUMN,
            default => Artboard::WIDTH - self::AREA_GAP - (self::AREA_GAP + 3 * self::LIST_COLUMN + 3 * self::AREA_GAP),
        };
    }
}
