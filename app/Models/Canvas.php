<?php

namespace App\Models;

use App\Enums\CanvasKind;
use App\Models\Concerns\NormalisesName;
use App\Services\Audit\Audited;
use Database\Factories\CanvasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organisation_id', 'kind', 'is_blank_template', 'name', 'archived_at'])]
class Canvas extends Model
{
    /** @use HasFactory<CanvasFactory> */
    use Audited, HasFactory, NormalisesName;

    protected $attributes = ['is_blank_template' => false];

    protected static function booted(): void
    {
        static::saving(function (self $canvas) {
            $canvas->scope_key = $canvas->kind === CanvasKind::Template ? 'template' : 'organisation:'.$canvas->organisation_id;
        });
    }

    protected $casts = [
        'kind' => CanvasKind::class,
        'is_blank_template' => 'boolean',
        'archived_at' => 'datetime',
    ];

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<AllocationSet, $this> */
    public function allocationSets(): HasMany
    {
        return $this->hasMany(AllocationSet::class);
    }
}
