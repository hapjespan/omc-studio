<?php

namespace App\Services\Allocation;

use App\Models\Element;
use App\Models\FiscalYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class Redistribution
{
    public function __construct(private readonly FinalisedYears $finalisedYears) {}

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
