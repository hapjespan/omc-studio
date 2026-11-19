<?php

namespace App\Models;

use App\Enums\StatementKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(timestamps: false)]
#[Fillable(['canvas_id', 'kind', 'title', 'body', 'position'])]
class Statement extends Model
{
    protected $casts = ['kind' => StatementKind::class, 'position' => 'integer'];
}
