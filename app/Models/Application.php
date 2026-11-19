<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use App\Services\Audit\Audited;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'name', 'supplier'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use Audited, HasFactory, NormalisesName;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
