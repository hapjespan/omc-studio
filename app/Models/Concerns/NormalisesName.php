<?php

namespace App\Models\Concerns;

trait NormalisesName
{
    public static function normalise(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    protected static function bootNormalisesName(): void
    {
        static::saving(function ($model) {
            $model->name_normalised = static::normalise($model->name);
        });
    }
}
