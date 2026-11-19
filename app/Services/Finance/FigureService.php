<?php

namespace App\Services\Finance;

use App\Calculation\FigureCalculator;
use App\Calculation\Figures;
use App\Models\Canvas;
use App\Services\Notices\SystemNotices;

class FigureService
{
    public function __construct(
        private CalculationInputBuilder $builder,
        private FigureCalculator $calculator,
        private SystemNotices $notices,
    ) {}

    /** @param array{costKind?: string|null, ownerId?: int|string|null, applicationId?: int|string|null} $filter */
    public function figures(Canvas $canvas, int $year, array $filter = []): ?Figures
    {
        $figures = $this->calculator->calculate($this->builder->build($canvas, $year, $filter));
        $failure = $figures->closingCheck['failures'][0] ?? null;
        if ($failure === null) {
            return $figures;
        }

        $filter = array_filter($filter, fn ($value) => $value !== null);
        ksort($filter);
        $this->notices->record('closing_check_failed', $canvas->id, [
            'year' => $year,
            'filter' => $filter,
            'check' => $failure['check'],
            'differenceCents' => $failure['differenceCents'],
        ]);

        return null;
    }
}
