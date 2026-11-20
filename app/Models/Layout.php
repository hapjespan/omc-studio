<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['canvas_id', 'area_id', 'element_id', 'x', 'y', 'width', 'height', 'z', 'style', 'format_version'])]
class Layout extends Model
{
    public const CREATED_AT = null;

    protected $casts = [
        'x' => 'integer',
        'y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'z' => 'integer',
        'style' => 'array',
        'format_version' => 'integer',
    ];
}
