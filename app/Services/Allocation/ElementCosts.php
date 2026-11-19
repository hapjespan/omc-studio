<?php

namespace App\Services\Allocation;

use App\Models\Canvas;
use Illuminate\Support\Facades\DB;

class ElementCosts
{
    /** @return array<string, array{years: list<int>, own: bool}> */
    public function for(Canvas $canvas): array
    {
        $parents = DB::table('elements')->where('canvas_id', $canvas->id)->pluck('parent_id', 'id');
        $lines = DB::table('allocation_lines')
            ->join('allocation_sets', 'allocation_sets.id', '=', 'allocation_lines.allocation_set_id')
            ->join('fiscal_years', 'fiscal_years.id', '=', 'allocation_sets.fiscal_year_id')
            ->where('allocation_sets.canvas_id', $canvas->id)
            ->get(['allocation_lines.element_id', 'fiscal_years.year']);

        /** @var array<string, array<int, int>> $years */
        $years = [];
        /** @var array<string, bool> $own */
        $own = [];

        foreach ($lines as $line) {
            for ($id = (string) $line->element_id; $id !== ''; $id = (string) ($parents[$id] ?? '')) {
                $years[$id][(int) $line->year] = (int) $line->year;
                $own[$id] = ($own[$id] ?? false) || $id === (string) $line->element_id;
            }
        }

        $costs = [];

        foreach ($years as $id => $found) {
            sort($found);
            $costs[$id] = ['years' => $found, 'own' => $own[$id]];
        }

        return $costs;
    }
}
