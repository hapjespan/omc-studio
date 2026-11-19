<?php

namespace App\Models;

use App\Enums\CanvasKind;
use App\Models\Concerns\NormalisesName;
use Database\Factories\CanvasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'kind', 'is_blank_template', 'name', 'archived_at'])]
class Canvas extends Model
{
    /** @use HasFactory<CanvasFactory> */
    use HasFactory, NormalisesName;

    protected $attributes = ['is_blank_template' => false];

    protected static function booted(): void
    {
        static::saving(function (self $canvas) {
            $canvas->scope_key = $canvas->kind === CanvasKind::Template ? 'template' : 'organisation:'.$canvas->organisation_id;
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => CanvasKind::class,
            'is_blank_template' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
