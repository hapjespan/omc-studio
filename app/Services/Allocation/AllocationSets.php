<?php

namespace App\Services\Allocation;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Rules\BasisPoints;
use App\Services\Audit\AuditTrail;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use App\Services\Undo\UndoStore;
use Illuminate\Validation\ValidationException;

class AllocationSets
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly FinalisedYears $finalisedYears,
        private readonly AuditTrail $trail,
        private readonly UndoStore $undo,
    ) {}

    /** @param list<array<string, mixed>> $lines */
    public function replace(Canvas $canvas, CostRecord $record, FiscalYear $year, array $lines): AllocationSet
    {
        return $this->writes->run([$canvas], function () use ($canvas, $record, $year, $lines) {
            $this->assertAllocatable($canvas, $record, $year);

            $values = $this->values($canvas, $lines);

            $keys = [
                'canvas_id' => $canvas->getKey(),
                'cost_record_id' => $record->getKey(),
                'fiscal_year_id' => $year->getKey(),
            ];
            $set = AllocationSet::where($keys)->first() ?? AllocationSet::withoutEvents(fn () => AllocationSet::create($keys));

            $before = $this->describe($set);

            $set->lines()->delete();
            AllocationLine::insert(array_map(fn (array $value, int $position) => [
                'allocation_set_id' => $set->getKey(),
                'element_id' => $value['id'],
                'basis_points' => $value['basis_points'],
                'position' => $position,
            ], $values, array_keys($values)));

            $this->trail->record($set, 'allocation_changed', ['lines' => [$before, $this->describe($set)]]);

            return $set;
        });
    }

    public function clear(Canvas $canvas, CostRecord $record, FiscalYear $year, User $user): string
    {
        return $this->writes->run([$canvas], function () use ($canvas, $record, $year, $user) {
            $canvas->loadMissing('organisation');
            $this->assertWritable($canvas);
            $this->finalisedYears->assertOpenInCanvas($canvas, $year);

            $set = AllocationSet::where('canvas_id', $canvas->getKey())
                ->where('cost_record_id', $record->getKey())
                ->where('fiscal_year_id', $year->getKey())
                ->first();

            if ($set === null) {
                $this->reject('no_set', ['year' => $year->year]);
            }

            $lines = $set->lines()->get();

            $token = $this->undo->remember(UndoStore::ALLOCATION_SET_CLEARED, [
                'canvas_id' => $canvas->getKey(),
                'allocation_sets' => [$set->getAttributes()],
                'allocation_lines' => $lines->map(fn (AllocationLine $line) => $line->getAttributes())->all(),
            ], $user);

            $set->lines()->delete();
            $set->delete();

            return $token;
        });
    }

    protected function assertAllocatable(Canvas $canvas, CostRecord $record, FiscalYear $year): void
    {
        $canvas->loadMissing('organisation');

        $this->assertWritable($canvas);
        $this->finalisedYears->assertOpenInCanvas($canvas, $year);

        $figures = YearlyFigure::where('cost_record_id', $record->getKey())->where('fiscal_year_id', $year->getKey())->exists();

        if (! $figures) {
            $this->reject('no_figures', ['year' => $year->year]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{id: string, basis_points: int}>
     */
    private function values(Canvas $canvas, array $lines): array
    {
        $targets = $this->targets($lines);
        $values = [];
        $total = 0;

        foreach ($lines as $line) {
            $id = $this->identifier($line);
            $element = $targets[$id] ?? null;

            if ($element === null || ! $element->carriesCosts($canvas)) {
                $this->reject('target_invalid', ['name' => (string) ($element->name ?? $id)]);
            }

            if (in_array($id, array_column($values, 'id'), true)) {
                $this->reject('target_repeated', ['name' => $element->name]);
            }

            $points = $this->points($line['basis_points'] ?? null);

            if ($points === null) {
                $this->reject('line_invalid', ['target' => $element->name]);
            }

            $total += $points;
            $values[] = ['id' => $id, 'basis_points' => $points];
        }

        if ($total !== BasisPoints::MAX) {
            $this->reject('sum_invalid', ['total' => $this->percentage($total)]);
        }

        return $values;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, Element>
     */
    private function targets(array $lines): array
    {
        $ids = array_filter(array_map(fn (array $line) => $this->identifier($line), $lines), fn (string $id) => $id !== '');

        return Element::query()->with('area')->whereIn('id', array_values($ids))->get()->keyBy('id')->all();
    }

    /** @param array<string, mixed> $line */
    private function identifier(array $line): string
    {
        $value = $line['element_id'] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    private function points(mixed $value): ?int
    {
        $points = match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^\d+$/', $value) === 1 => (int) $value,
            default => null,
        };

        return $points !== null && $points >= 1 && $points <= BasisPoints::MAX ? $points : null;
    }

    private function percentage(int $basisPoints): string
    {
        return sprintf('%d,%02d%%', intdiv($basisPoints, 100), $basisPoints % 100);
    }

    /** @return list<array{target: string, percentage: string}> */
    private function describe(AllocationSet $set): array
    {
        return $set->lines()->with('element')->get()
            ->map(fn (AllocationLine $line) => ['target' => (string) $line->element?->name, 'percentage' => $this->percentage($line->basis_points)])
            ->all();
    }

    /** @param array<string, int|string> $replace */
    private function reject(string $key, array $replace): never
    {
        throw ValidationException::withMessages(['rule' => __('allocation.'.$key, $replace)]);
    }
}
