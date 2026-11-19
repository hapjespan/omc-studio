<?php

namespace App\Services\Operations\Steps;

use App\Enums\CanvasKind;
use App\Models\Canvas;

class BlankTemplateStep
{
    public function __invoke(): void
    {
        Canvas::firstOrCreate(
            ['is_blank_template' => true],
            ['kind' => CanvasKind::Template, 'organisation_id' => null, 'name' => __('templates.blank_name')],
        );
    }
}
