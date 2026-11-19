<?php

namespace App\Models;

use App\Services\Audit\Audited;
use Database\Factories\AllocationSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['canvas_id', 'cost_record_id', 'fiscal_year_id'])]
class AllocationSet extends Model
{
    /** @use HasFactory<AllocationSetFactory> */
    use Audited, HasFactory;

    /** @return BelongsTo<Canvas, $this> */
    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }

    /** @return BelongsTo<CostRecord, $this> */
    public function costRecord(): BelongsTo
    {
        return $this->belongsTo(CostRecord::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return HasMany<AllocationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(AllocationLine::class)->orderBy('position');
    }
}
