<?php

namespace App\Models\Concerns;

use App\Support\NameNormaliser;

trait NormalisesName
{
    public static function normalise(string $value): string
    {
        return NameNormaliser::normalise($value);
    }

    protected static function bootNormalisesName(): void
    {
        static::saving(function ($model) {
            $model->name_normalised = static::normalise($model->name);
        });
    }
}
