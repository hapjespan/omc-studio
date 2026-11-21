<?php

namespace App\Services\Allocation;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use App\Services\Structure\ElementArchive;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class Redistribution
{
    use WritableGuard;

    public function __construct(
        private readonly FinalisedYears $finalisedYears,
        private readonly SerialisedWrite $writes,
        private readonly ElementArchive $archive,
        private readonly AuditTrail $trail,
        private readonly AuditContext $context,
    ) {}

    /** @return array{years: list<array{year: int, records: int, basis_points: int, affected: list<array{cost_record_id: string, name: string, basis_points: int}>}>} */
    public function preview(Element $element): array
    {
        $years = FiscalYear::whereIn('id', $this->affectedLines($element)->pluck('fiscal_year_id')->unique())->orderBy('year')->get();
        $result = [];

        foreach ($years as $year) {
            $this->finalisedYears->assertOpenInCanvas($element->canvas, $year);

            $affected = $this->affectedLines($element)->where('fiscal_year_id', $year->id)->groupBy('cost_record_id')
                ->map(fn (Collection $lines, string $id) => ['cost_record_id' => $id, 'name' => (string) $lines[0]->name, 'basis_points' => (int) $lines->sum('basis_points')])
                ->sortBy('name')->values()->all();

            $result[] = ['year' => $year->year, 'records' => count($affected), 'basis_points' => array_sum(array_column($affected, 'basis_points')), 'affected' => $affected];
        }

        return ['years' => $result];
    }

    /** @param array<array-key, array{mode?: string, target?: string|null}> $choices */
    public function apply(Element $element, array $choices): void
    {
        $canvas = $element->canvas;

        $this->writes->run([$canvas], function () use ($canvas, $element, $choices): void {
            $this->assertStructureWritable($canvas);

            $subtree = $this->subtreeIds($element);
            $names = DB::table('elements')->where('canvas_id', $canvas->id)->pluck('name', 'id');
            $years = FiscalYear::whereIn('id', $this->affectedLines($element)->pluck('fiscal_year_id')->unique())->orderBy('year')->get();

            foreach ($years as $year) {
                $this->finalisedYears->assertOpenInCanvas($canvas, $year);

                $target = $this->chosenTarget($canvas, $subtree, $choices[$year->year] ?? [], $year->year);

                $this->context->during('redistributed', function () use ($canvas, $year, $subtree, $target, $names) {
                    foreach ($this->affectedSets($canvas, (int) $year->id, $subtree) as $set) {
                        $target === null ? $this->drop($set) : $this->move($set, $subtree, $target, $names);
                    }
                });
            }

            $this->archive->archive($element);
        });
    }

    /** @return list<string> */
    public function subtreeIds(Element $element): array
    {
        $ids = [(string) $element->id];
        $level = $ids;

        while ($level !== []) {
            $level = DB::table('elements')->whereIn('parent_id', $level)->pluck('id')->map(fn ($id) => (string) $id)->all();
            array_push($ids, ...$level);
        }

        return $ids;
    }

    /**
     * @param  list<string>  $subtree
     * @param  array{mode?: string, target?: string|null}  $choice
     */
    private function chosenTarget(Canvas $canvas, array $subtree, array $choice, int $year): ?Element
    {
        if (($choice['mode'] ?? null) === 'unknown') {
            return null;
        }

        if (($choice['mode'] ?? null) !== 'move' || ($choice['target'] ?? null) === null) {
            throw ValidationException::withMessages(['rule' => __('rule.redistribution_choice_required', ['year' => $year])]);
        }

        $target = Element::with('area')->findOrFail($choice['target']);

        if (in_array((string) $target->id, $subtree, true)) {
            $this->refuse('redistribution_target_inside');
        }

        if ($target->canvas_id !== $canvas->id || $target->archived_at !== null || $target->is_unknown_allocation || $target->area->carries_costs !== true) {
            throw ValidationException::withMessages(['rule' => __('rule.redistribution_target_cannot_carry', ['name' => $target->name])]);
        }

        return $target;
    }

    /**
     * @param  list<string>  $subtree
     * @return Collection<int, AllocationSet>
     */
    private function affectedSets(Canvas $canvas, int $year, array $subtree): Collection
    {
        return AllocationSet::with('lines')
            ->where('canvas_id', $canvas->id)
            ->where('fiscal_year_id', $year)
            ->whereHas('lines', fn ($query) => $query->whereIn('element_id', $subtree))
            ->orderBy('id')
            ->get();
    }

    private function drop(AllocationSet $set): void
    {
        AllocationLine::where('allocation_set_id', $set->id)->delete();
        $set->delete();
    }

    /**
     * @param  list<string>  $subtree
     * @param  Collection<int|string, mixed>  $names
     */
    private function move(AllocationSet $set, array $subtree, Element $target, Collection $names): void
    {
        $lines = $set->lines;
        $before = $this->describe($lines, $names);
        $affected = $lines->filter(fn (AllocationLine $line) => in_array((string) $line->element_id, $subtree, true));
        $existing = $lines->first(fn (AllocationLine $line) => (string) $line->element_id === (string) $target->id);
        $keeper = $existing ?? $affected->first();
        $sum = (int) $affected->sum('basis_points');

        if ($keeper === null) {
            return;
        }

        if ($existing === null) {
            $keeper->element_id = $target->id;
            $keeper->basis_points = $sum;
        } else {
            $existing->basis_points += $sum;
        }

        $dropped = $affected->reject(fn (AllocationLine $line) => $line->is($keeper));
        $dropped->each(fn (AllocationLine $line) => $line->delete());

        foreach ($lines->reject(fn (AllocationLine $line) => $dropped->contains($line))->values() as $position => $line) {
            $line->position = $position;
            $line->save();
        }

        $this->trail->record($set, $this->context->action('changed'), ['lines' => [$before, $this->describe($set->lines()->get(), $names)]]);
    }

    /**
     * @param  Collection<int, AllocationLine>  $lines
     * @param  Collection<int|string, mixed>  $names
     * @return list<array{target: string, basis_points: int}>
     */
    private function describe(Collection $lines, Collection $names): array
    {
        return $lines->map(fn (AllocationLine $line) => ['target' => (string) $names[$line->element_id], 'basis_points' => $line->basis_points])->values()->all();
    }

    /** @return Collection<int, stdClass> */
    private function affectedLines(Element $element): Collection
    {
        return DB::table('allocation_lines')
            ->join('allocation_sets', 'allocation_sets.id', '=', 'allocation_lines.allocation_set_id')
            ->join('cost_records', 'cost_records.id', '=', 'allocation_sets.cost_record_id')
            ->where('allocation_sets.canvas_id', $element->canvas_id)
            ->whereIn('allocation_lines.element_id', $this->subtreeIds($element))
            ->get(['allocation_sets.fiscal_year_id', 'cost_records.id as cost_record_id', 'cost_records.name', 'allocation_lines.basis_points']);
    }
}
