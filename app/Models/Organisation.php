<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use App\Services\Audit\Audited;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'archived_at'])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use Audited, HasFactory, NormalisesName;

    protected static function booted(): void
    {
        static::saving(function (self $organisation) {
            $organisation->slug ??= Str::slug($organisation->name);
        });
    }

    protected $casts = ['archived_at' => 'datetime'];

    /** @return HasMany<FiscalYear, $this> */
    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    /** @return HasMany<Owner, $this> */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<CostRecord, $this> */
    public function costRecords(): HasMany
    {
        return $this->hasMany(CostRecord::class);
    }
}
