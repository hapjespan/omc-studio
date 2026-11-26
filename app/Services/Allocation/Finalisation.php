<?php

namespace App\Services\Allocation;

use App\Calculation\FigureCalculator;
use App\Models\AllocationLine;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Services\Audit\AuditContext;
use App\Services\Finance\CalculationInputBuilder;
use App\Services\Notices\SystemNotices;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Finalisation
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly CalculationInputBuilder $builder,
        private readonly FigureCalculator $calculator,
        private readonly AuditContext $context,
        private readonly SystemNotices $notices,
    ) {}

    public function finalise(Canvas $canvas, FiscalYear $year): FinalisedYear
    {
        $result = $this->writes->run([$this->organisation($canvas), $canvas], function () use ($canvas, $year): FinalisedYear|array {
            $this->assertWritable($canvas);

            if ($this->row($canvas, $year) !== null) {
                $this->refuseWith((string) __('rule.year_already_finalised', ['year' => $year->year]));
            }

            $unknown = $this->unknownAllocation($canvas, $year);

            if ($unknown !== []) {
                $this->refuseWith((string) trans_choice('rule.unknown_allocation_remaining', count($unknown), ['names' => implode(', ', $unknown)]));
            }

            $figures = $this->calculator->calculate($this->builder->build($canvas, $year->year));

            if (($figures->closingCheck['passed'] ?? false) !== true) {
                return $figures->closingCheck['failures'][0];
            }

            return $this->context->during('finalised', fn () => FinalisedYear::create([
                'canvas_id' => $canvas->getKey(),
                'fiscal_year_id' => $year->getKey(),
                'finalised_at' => now(),
                'finalised_by' => Auth::id(),
            ]));
        });

        if ($result instanceof FinalisedYear) {
            return $result;
        }

        $this->notices->recordClosingCheckFailed($canvas->getKey(), $year->year, [], $result);
        $this->refuseWith((string) __('rule.closing_check_failed'));
    }

    public function reopen(Canvas $canvas, FiscalYear $year): void
    {
        $this->writes->run([$this->organisation($canvas), $canvas], function () use ($canvas, $year): void {
            $this->assertWritable($canvas);

            $row = $this->row($canvas, $year);

            if ($row === null) {
                $this->refuseWith((string) __('rule.year_not_finalised', ['year' => $year->year]));
            }

            $this->context->during('reopened', fn () => $row->delete());
        });
    }

    private function organisation(Canvas $canvas): Organisation
    {
        return Organisation::findOrFail($canvas->organisation_id);
    }

    private function row(Canvas $canvas, FiscalYear $year): ?FinalisedYear
    {
        return FinalisedYear::where('canvas_id', $canvas->getKey())->where('fiscal_year_id', $year->getKey())->first();
    }

    /** @return list<string> */
    private function unknownAllocation(Canvas $canvas, FiscalYear $year): array
    {
        $allocated = AllocationLine::query()
            ->join('allocation_sets', 'allocation_sets.id', '=', 'allocation_lines.allocation_set_id')
            ->where('allocation_sets.canvas_id', $canvas->getKey())
            ->where('allocation_sets.fiscal_year_id', $year->getKey())
            ->select('allocation_sets.cost_record_id');

        return CostRecord::query()
            ->where('organisation_id', $canvas->organisation_id)
            ->whereIn('id', YearlyFigure::where('fiscal_year_id', $year->getKey())->select('cost_record_id'))
            ->whereNotIn('id', $allocated)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (mixed $name) => (string) $name)
            ->all();
    }

    private function refuseWith(string $message): never
    {
        throw ValidationException::withMessages(['rule' => $message]);
    }
}
