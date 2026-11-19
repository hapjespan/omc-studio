<?php

namespace App\Models;

use App\Enums\CostKind;
use App\Services\Audit\Audited;
use Database\Factories\CostRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organisation_id', 'code', 'name', 'cost_kind', 'supplier', 'owner_id', 'cost_centre',
    'application_id', 'description', 'archived_at',
])]
class CostRecord extends Model
{
    /** @use HasFactory<CostRecordFactory> */
    use Audited, HasFactory, HasUuids;

    protected $casts = ['cost_kind' => CostKind::class, 'archived_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function (self $record) {
            $code = $record->code === null ? '' : trim($record->code);
            $record->code = $code === '' ? null : $code;
            $record->code_normalised = $record->code === null ? null : mb_strtolower($record->code);
        });
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Owner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return HasMany<YearlyFigure, $this> */
    public function yearlyFigures(): HasMany
    {
        return $this->hasMany(YearlyFigure::class);
    }
}
