<?php

namespace App\Services\Layout;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Layout;

class LayoutSnapshot
{
    /** @return array<string, array<string, mixed>> */
    public function for(Canvas $canvas): array
    {
        $areaKeys = Area::query()->pluck('key', 'id');
        $layouts = [];

        foreach (Layout::query()->where('canvas_id', $canvas->getKey())->get() as $row) {
            $layouts[$row->element_id !== null ? (string) $row->element_id : $areaKeys[$row->area_id]] = [
                'mode' => 'manual',
                'x' => $row->x,
                'y' => $row->y,
                'width' => $row->width,
                'height' => $row->height,
                'z' => $row->z,
                'style' => $row->style === [] ? new \stdClass : $row->style,
            ];
        }

        return $layouts;
    }
}
