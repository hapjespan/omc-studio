<?php

namespace App\Services\Allocation;

use App\Models\Canvas;
use Illuminate\Support\Facades\DB;

class ElementCosts
{
    /** @return array<string, array{cents: int, records: int, years: list<int>, own: bool}> */
    public function for(Canvas $canvas, ?int $year): array
    {
        $parents = DB::table('elements')->where('canvas_id', $canvas->id)->pluck('parent_id', 'id');
        $lines = DB::table('allocation_lines')
            ->join('allocation_sets', 'allocation_sets.id', '=', 'allocation_lines.allocation_set_id')
            ->join('fiscal_years', 'fiscal_years.id', '=', 'allocation_sets.fiscal_year_id')
            ->leftJoin('yearly_figures', fn ($join) => $join->on('yearly_figures.cost_record_id', '=', 'allocation_sets.cost_record_id')->on('yearly_figures.fiscal_year_id', '=', 'allocation_sets.fiscal_year_id'))
            ->where('allocation_sets.canvas_id', $canvas->id)
            ->get(['allocation_lines.element_id', 'allocation_lines.basis_points', 'allocation_sets.cost_record_id', 'fiscal_years.year', 'yearly_figures.budget_cents']);

        $costs = [];

        foreach ($lines as $line) {
            for ($id = (string) $line->element_id; $id !== ''; $id = (string) ($parents[$id] ?? '')) {
                $entry = &$costs[$id];
                $entry ??= ['cents' => 0, 'records' => [], 'years' => [], 'own' => false];
                $entry['own'] = $entry['own'] || $id === (string) $line->element_id;
                $entry['years'][(int) $line->year] = (int) $line->year;

                if ((int) $line->year === $year) {
                    $entry['cents'] += intdiv((int) $line->basis_points * (int) $line->budget_cents + 5000, 10000);
                    $entry['records'][$line->cost_record_id] = true;
                }

                unset($entry);
            }
        }

        return array_map(fn (array $entry) => ['cents' => $entry['cents'], 'records' => count($entry['records']), 'years' => array_values(collect($entry['years'])->sort()->all()), 'own' => $entry['own']], $costs);
    }
}
