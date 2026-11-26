<?php

namespace App\Services\Allocation;

use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use Illuminate\Validation\ValidationException;

class FinalisedYears
{
    public function finalisedInCanvas(Canvas $canvas, FiscalYear $year): bool
    {
        return FinalisedYear::where('canvas_id', $canvas->getKey())->where('fiscal_year_id', $year->getKey())->exists();
    }

    public function finalisedInOrganisation(FiscalYear $year): ?string
    {
        return FinalisedYear::query()
            ->join('canvases', 'canvases.id', '=', 'finalised_years.canvas_id')
            ->where('finalised_years.fiscal_year_id', $year->getKey())
            ->orderBy('canvases.id')
            ->value('canvases.name');
    }

    public function assertOpenInCanvas(Canvas $canvas, FiscalYear $year): void
    {
        if ($this->finalisedInCanvas($canvas, $year)) {
            $this->refuse('year_finalised', ['year' => $year->year]);
        }
    }

    public function assertOpenInOrganisation(FiscalYear $year): void
    {
        $canvas = $this->finalisedInOrganisation($year);

        if ($canvas !== null) {
            $this->refuse('year_finalised_in_canvas', ['year' => $year->year, 'canvas' => $canvas]);
        }
    }

    /** @param  array<string, int|string>  $replace */
    private function refuse(string $rule, array $replace): never
    {
        throw ValidationException::withMessages(['rule' => __('rule.'.$rule, $replace)]);
    }
}
