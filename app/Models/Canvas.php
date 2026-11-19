<?php

namespace App\Models;

use App\Enums\CanvasKind;
use App\Enums\Role;
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

    public function acceptsEntryBy(?User $user): bool
    {
        return $user?->role === Role::Administrator && ($this->kind === CanvasKind::Template || $this->archived_at === null);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<Element, $this> */
    public function elements(): HasMany
    {
        return $this->hasMany(Element::class);
    }

    /** @return HasMany<Statement, $this> */
    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class);
    }
}
