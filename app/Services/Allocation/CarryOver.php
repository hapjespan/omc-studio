<?php

namespace App\Services\Allocation;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CarryOver
{
    use WritableGuard;

    public function __construct(private readonly SerialisedWrite $writes, private readonly FinalisedYears $finalisedYears) {}

    /** @return array{copied: list<array{name: string, set: bool, figures: int}>, skipped: list<array{name: string, reason: string}>, manual: list<array{name: string}>} */
    public function run(Canvas $canvas, FiscalYear $source, FiscalYear $target, bool $withFigures): array
    {
        $organisation = Organisation::findOrFail($canvas->organisation_id);

        return $this->writes->run([$organisation], function () use ($canvas, $source, $target, $withFigures): array {
            $this->assertWritable($canvas);
            $this->assertTargetOpen($canvas, $target, $withFigures);

            $sourceSets = $this->sets($canvas, $source);
            $targetSets = $this->sets($canvas, $target);
            $sourceFigures = $this->figures($source);
            $targetFigures = $this->figures($target);
            $targets = $this->targetIds($canvas);

            $copied = [];
            $skipped = [];
            $manual = [];

            foreach ($this->candidates($sourceSets, $sourceFigures, $withFigures) as $record) {
                $key = (string) $record->id;
                $set = $sourceSets[$key] ?? null;

                if ($set !== null && $set->lines->contains(fn (AllocationLine $line) => ! in_array((string) $line->element_id, $targets, true))) {
                    $manual[] = ['name' => (string) $record->name];

                    continue;
                }

                if (isset($targetSets[$key]) || ($withFigures && isset($targetFigures[$key]))) {
                    $skipped[] = ['name' => (string) $record->name, 'reason' => (string) __('carry_over.target_not_empty')];

                    continue;
                }

                if (! $withFigures && ! isset($targetFigures[$key])) {
                    $skipped[] = ['name' => (string) $record->name, 'reason' => (string) __('carry_over.no_figures', ['year' => $target->year])];

                    continue;
                }

                $archived = $record->archived_at !== null;
                $figures = $withFigures && ! $archived ? $this->copyFigures($sourceFigures[$key] ?? [], $target) : 0;

                if ($set !== null) {
                    $this->copySet($set, $target);
                }

                if ($withFigures && $archived) {
                    $skipped[] = ['name' => (string) $record->name, 'reason' => (string) __('carry_over.archived')];
                }

                if ($set !== null || $figures > 0) {
                    $copied[] = ['name' => (string) $record->name, 'set' => $set !== null, 'figures' => $figures];
                }
            }

            return ['copied' => $copied, 'skipped' => $skipped, 'manual' => $manual];
        });
    }

    /** @return array<string, AllocationSet> */
    private function sets(Canvas $canvas, FiscalYear $year): array
    {
        $sets = [];

        foreach (AllocationSet::with('lines')->where('canvas_id', $canvas->getKey())->where('fiscal_year_id', $year->getKey())->get() as $set) {
            $sets[(string) $set->cost_record_id] = $set;
        }

        return $sets;
    }

    /** @return array<string, list<YearlyFigure>> */
    private function figures(FiscalYear $year): array
    {
        $figures = [];

        foreach (YearlyFigure::where('fiscal_year_id', $year->getKey())->orderBy('id')->get() as $figure) {
            $figures[(string) $figure->cost_record_id][] = $figure;
        }

        return $figures;
    }

    /** @return list<string> */
    private function targetIds(Canvas $canvas): array
    {
        return Element::query()
            ->where('canvas_id', $canvas->getKey())
            ->whereNull('archived_at')
            ->where('is_unknown_allocation', false)
            ->whereIn('area_id', Area::where('carries_costs', true)->select('id'))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @param  array<string, AllocationSet>  $sourceSets
     * @param  array<string, list<YearlyFigure>>  $sourceFigures
     * @return Collection<int, CostRecord>
     */
    private function candidates(array $sourceSets, array $sourceFigures, bool $withFigures): Collection
    {
        $keys = $withFigures ? array_unique(array_merge(array_keys($sourceSets), array_keys($sourceFigures))) : array_keys($sourceSets);

        return CostRecord::whereIn('id', $keys)->orderBy('name')->get();
    }

    /** @param  list<YearlyFigure>  $figures */
    private function copyFigures(array $figures, FiscalYear $target): int
    {
        foreach ($figures as $figure) {
            $copy = $figure->replicate();
            $copy->fiscal_year_id = $target->getKey();
            $copy->save();
        }

        return count($figures);
    }

    private function copySet(AllocationSet $set, FiscalYear $target): void
    {
        $copy = AllocationSet::create([
            'canvas_id' => $set->canvas_id,
            'cost_record_id' => $set->cost_record_id,
            'fiscal_year_id' => $target->getKey(),
        ]);

        foreach ($set->lines as $line) {
            AllocationLine::create([
                'allocation_set_id' => $copy->id,
                'element_id' => $line->element_id,
                'basis_points' => $line->basis_points,
                'position' => $line->position,
            ]);
        }
    }

    private function assertTargetOpen(Canvas $canvas, FiscalYear $target, bool $withFigures): void
    {
        $name = $this->finalisedYears->finalisedInCanvas($canvas, $target) ? $canvas->name : ($withFigures ? $this->finalisedYears->finalisedInOrganisation($target) : null);

        if ($name !== null) {
            throw ValidationException::withMessages(['rule' => __('rule.year_finalised_in_canvas', ['year' => $target->year, 'canvas' => $name])]);
        }
    }
}
