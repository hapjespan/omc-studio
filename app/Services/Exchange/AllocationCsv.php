<?php

namespace App\Services\Exchange;

use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Services\Exchange\Csv\CsvWriter;

class AllocationCsv
{
    public const COLUMNS = ['cost_record_code', 'year', 'target_path', 'percentage'];

    public function write(Canvas $canvas, mixed $handle): void
    {
        $writer = new CsvWriter($handle);
        $writer->write(Headers::row('allocation', self::COLUMNS));

        $paths = new TargetPaths($canvas);
        $sets = AllocationSet::query()
            ->join('cost_records', 'cost_records.id', '=', 'allocation_sets.cost_record_id')
            ->join('fiscal_years', 'fiscal_years.id', '=', 'allocation_sets.fiscal_year_id')
            ->where('allocation_sets.canvas_id', $canvas->getKey())
            ->whereNotNull('cost_records.code')
            ->orderBy('cost_records.code_normalised')
            ->orderBy('fiscal_years.year')
            ->with('lines.element')
            ->get(['allocation_sets.*', 'cost_records.code as export_code', 'fiscal_years.year as export_year']);

        foreach ($sets as $set) {
            foreach ($set->lines as $line) {
                $writer->write([$set->getAttribute('export_code'), $set->getAttribute('export_year'), $paths->of($line->element), CsvWriter::decimal($line->basis_points)]);
            }
        }
    }

    public function omitted(Canvas $canvas): int
    {
        return CostRecord::query()->whereNull('code')
            ->whereIn('id', AllocationSet::query()->where('canvas_id', $canvas->getKey())->select('cost_record_id'))
            ->count();
    }
}
