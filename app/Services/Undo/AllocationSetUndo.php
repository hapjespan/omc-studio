<?php

namespace App\Services\Undo;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\UndoRecord;
use App\Models\YearlyFigure;
use App\Services\Allocation\FinalisedYears;
use App\Services\Audit\AuditContext;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class AllocationSetUndo
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly FinalisedYears $finalisedYears,
        private readonly AuditContext $context,
    ) {}

    public function restore(UndoRecord $record): void
    {
        /** @var array{canvas_id: int, allocation_sets: list<array<string, mixed>>, allocation_lines: list<array<string, mixed>>} $payload */
        $payload = (array) $record->payload;
        $canvas = Canvas::query()->with('organisation')->whereKey($payload['canvas_id'])->first();

        if ($canvas === null) {
            $this->refuse('undo_parent_gone');
        }

        $this->writes->run([$canvas], function () use ($canvas, $payload, $record) {
            $this->assertWritable($canvas);
            $this->assertEveryRuleStillHolds($canvas, $payload['allocation_sets'][0], $payload['allocation_lines']);

            $this->context->during('undone', function () use ($payload) {
                (new AllocationSet)->forceFill($payload['allocation_sets'][0])->save();

                foreach ($payload['allocation_lines'] as $row) {
                    (new AllocationLine)->forceFill($row)->save();
                }
            });

            $record->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $set
     * @param  list<array<string, mixed>>  $lines
     */
    private function assertEveryRuleStillHolds(Canvas $canvas, array $set, array $lines): void
    {
        $year = FiscalYear::query()->whereKey($set['fiscal_year_id'])->first();

        if ($year === null) {
            $this->refuse('undo_parent_gone');
        }

        $this->finalisedYears->assertOpenInCanvas($canvas, $year);

        $taken = AllocationSet::where('canvas_id', $canvas->getKey())
            ->where('cost_record_id', $set['cost_record_id'])
            ->where('fiscal_year_id', $year->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['rule' => __('allocation.undo_set_exists')]);
        }

        if (! YearlyFigure::where('cost_record_id', $set['cost_record_id'])->where('fiscal_year_id', $year->getKey())->exists()) {
            throw ValidationException::withMessages(['rule' => __('allocation.no_figures', ['year' => $year->year])]);
        }

        $elements = Element::query()->with('area')->whereKey(array_column($lines, 'element_id'))->get()->keyBy('id');

        foreach ($lines as $line) {
            $element = $elements->get($line['element_id']);

            if ($element === null || ! $element->carriesCosts($canvas)) {
                throw ValidationException::withMessages(['rule' => __('allocation.target_invalid', ['name' => (string) ($element->name ?? $line['element_id'])])]);
            }
        }
    }
}
