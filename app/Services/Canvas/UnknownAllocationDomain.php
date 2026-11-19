<?php

namespace App\Services\Canvas;

use App\Enums\CanvasKind;
use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;

class UnknownAllocationDomain
{
    private const LAST_POSITION = 1000000;

    public static function ensure(Canvas $canvas): ?Element
    {
        if ($canvas->kind === CanvasKind::Template) {
            return null;
        }

        return Element::firstOrCreate(
            ['canvas_id' => $canvas->id, 'is_unknown_allocation' => true],
            [
                'area_id' => Area::where('key', 'supporting_activities')->firstOrFail()->id,
                'kind' => ElementKind::Domain,
                'name' => __('elements.unknown_allocation'),
                'position' => self::LAST_POSITION,
            ],
        );
    }
}
