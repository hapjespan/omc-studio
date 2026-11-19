<?php

namespace App\Models;

use App\Services\Audit\Audited;
use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'year'])]
class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use Audited, HasFactory;

    protected $casts = ['year' => 'integer'];

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
