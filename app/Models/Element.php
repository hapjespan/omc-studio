<?php

namespace App\Models;

use App\Enums\ElementKind;
use App\Models\Concerns\NormalisesName;
use Database\Factories\ElementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'canvas_id', 'area_id', 'parent_id', 'kind', 'name', 'description', 'modality_id',
    'rationale', 'icon', 'position', 'is_unknown_allocation', 'archived_at',
])]
class Element extends Model
{
    /** @use HasFactory<ElementFactory> */
    use HasFactory, HasUuids, NormalisesName;

    /** @var list<string> */
    protected $touches = ['canvas'];

    protected $attributes = ['position' => 0, 'is_unknown_allocation' => false];

    protected $casts = [
        'kind' => ElementKind::class,
        'position' => 'integer',
        'is_unknown_allocation' => 'boolean',
        'archived_at' => 'datetime',
    ];

    /** @return BelongsTo<Canvas, $this> */
    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
