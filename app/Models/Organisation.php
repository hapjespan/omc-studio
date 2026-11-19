<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'logo_path', 'archived_at'])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, NormalisesName;

    protected static function booted(): void
    {
        static::saving(function (self $organisation) {
            $organisation->slug ??= Str::slug($organisation->name);
        });
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
