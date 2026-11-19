<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use App\Services\Audit\Audited;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'name'])]
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use Audited, HasFactory, NormalisesName;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
