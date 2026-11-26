<?php

namespace App\Models;

use App\Services\Audit\Audited;
use Database\Factories\FinalisedYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['canvas_id', 'fiscal_year_id', 'finalised_at', 'finalised_by', 'imported'])]
class FinalisedYear extends Model
{
    /** @use HasFactory<FinalisedYearFactory> */
    use Audited, HasFactory;

    public $timestamps = false;

    protected $attributes = ['imported' => false];

    protected $casts = ['finalised_at' => 'datetime', 'imported' => 'boolean'];

    /** @return BelongsTo<Canvas, $this> */
    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return BelongsTo<User, $this> */
    public function finaliser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalised_by');
    }
}
