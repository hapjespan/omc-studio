<?php

namespace App\Services\Finance;

use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class FiscalYears
{
    use WritableGuard;

    public function __construct(private readonly SerialisedWrite $writes) {}

    public function add(Organisation $organisation, mixed $year): FiscalYear
    {
        return $this->writes->run([$organisation], function () use ($organisation, $year) {
            $this->assertWritable($organisation);

            $number = is_int($year) || (is_string($year) && ctype_digit($year)) ? (int) $year : 0;

            if ($number < 2000 || $number > 2100 || $organisation->fiscalYears()->where('year', $number)->exists()) {
                throw ValidationException::withMessages(['year' => __('fiscal_years.year_invalid')]);
            }

            return $organisation->fiscalYears()->create(['year' => $number]);
        });
    }

    public function delete(FiscalYear $fiscalYear): void
    {
        $this->writes->run([$fiscalYear->organisation], function () use ($fiscalYear) {
            $this->assertWritable($fiscalYear->organisation);

            $used = YearlyFigure::where('fiscal_year_id', $fiscalYear->getKey())->exists()
                || FinalisedYear::where('fiscal_year_id', $fiscalYear->getKey())->exists();

            if ($used) {
                throw ValidationException::withMessages(['rule' => __('fiscal_years.has_figures')]);
            }

            $fiscalYear->delete();
        });
    }
}
