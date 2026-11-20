<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class RedistributionRequired extends ValidationException
{
    /** @var list<array{fiscal_year_id: int, year: int, cost_records: int, basis_points: int}> */
    public array $years = [];

    /** @param list<array{fiscal_year_id: int, year: int, cost_records: int, basis_points: int}> $years */
    public static function for(array $years): self
    {
        $refusal = static::withMessages(['rule' => __('rule.redistribution_required')]);
        $refusal->years = $years;

        return $refusal;
    }
}
