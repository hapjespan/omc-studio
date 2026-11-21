<?php

namespace App\Services\Layout;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Support\LayoutLimits;

class MovedBlockFit
{
    public function __construct(private readonly LayoutRows $rows) {}

    /**
     * @param  ?array<string, mixed>  $layout
     * @return ?array<string, mixed>
     */
    public function apply(Canvas $canvas, Element $element, Element $target, ?array $layout): ?array
    {
        if ($layout === null || ! $this->isBox($layout)) {
            return $layout;
        }

        $targetWidth = $this->targetWidth($canvas, $target);
        $width = min((int) $layout['width'], $targetWidth);

        $this->fitChildren($canvas, $element, $width);

        return [
            ...$layout,
            'x' => max(0, min((int) $layout['x'], $targetWidth - $width)),
            'y' => max(0, (int) $layout['y']),
            'width' => $width,
        ];
    }

    private function targetWidth(Canvas $canvas, Element $target): int
    {
        $areaKeys = Area::query()->pluck('key', 'id');
        $areaWidths = [];
        $elementWidths = [];

        foreach (Layout::query()->where('canvas_id', $canvas->getKey())->get(['element_id', 'area_id', 'width']) as $row) {
            if ($row->element_id === null) {
                $areaWidths[(string) $areaKeys[$row->area_id]] = $row->width;
            } else {
                $elementWidths[(string) $row->element_id] = $row->width;
            }
        }

        return AutomaticWidths::compute($this->rows->elements($canvas), $areaWidths, $elementWidths)[(string) $target->getKey()];
    }

    private function fitChildren(Canvas $canvas, Element $element, int $width): void
    {
        $children = Element::query()
            ->where('parent_id', $element->getKey())
            ->whereNull('archived_at')
            ->pluck('id')
            ->all();

        $rows = Layout::query()->where('canvas_id', $canvas->getKey())->whereIn('element_id', $children)->get();

        foreach ($rows as $row) {
            $x = min($row->x, max(0, $width - $row->width));

            $row->update(['x' => $x, 'width' => max(LayoutLimits::minWidth(), min($row->width, $width - $x))]);
        }
    }

    /** @param array<string, mixed> $layout */
    private function isBox(array $layout): bool
    {
        return is_int($layout['x'] ?? null) && is_int($layout['y'] ?? null) && is_int($layout['width'] ?? null);
    }
}
