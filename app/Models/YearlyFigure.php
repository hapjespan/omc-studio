<?php

namespace App\Models;

use App\Services\Audit\Audited;
use Database\Factories\YearlyFigureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cost_record_id', 'fiscal_year_id', 'budget_cents', 'forecast_cents', 'run_bp', 'change_bp',
    'fte_hundredths', 'reference_date', 'remark',
])]
class YearlyFigure extends Model
{
    /** @use HasFactory<YearlyFigureFactory> */
    use Audited, HasFactory;

    protected $casts = [
        'budget_cents' => 'integer',
        'forecast_cents' => 'integer',
        'run_bp' => 'integer',
        'change_bp' => 'integer',
        'fte_hundredths' => 'integer',
        'reference_date' => 'date',
    ];

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
}
