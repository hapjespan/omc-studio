<?php

namespace App\Services\Exchange;

use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Services\Exchange\Csv\CsvWriter;

class CostRecordCsv
{
    public function write(Organisation $organisation, mixed $handle): void
    {
        $writer = new CsvWriter($handle);
        $writer->write(Headers::row('cost_record', CostRecordImport::COLUMNS));

        $years = FiscalYear::query()->where('organisation_id', $organisation->getKey())->pluck('year', 'id');
        $kinds = array_map('ucfirst', array_flip(array_map(fn ($kind) => $kind->value, CostRecordRowValidator::kinds())));

        $records = CostRecord::query()->where('organisation_id', $organisation->getKey())->whereNotNull('code')
            ->with(['owner', 'application', 'yearlyFigures'])->orderBy('code_normalised')->lazy(500);

        foreach ($records as $record) {
            $base = [$record->code, $record->name, $kinds[$record->cost_kind->value], $record->supplier, $record->owner?->name, $record->cost_centre, $record->application?->name];
            $figures = $record->yearlyFigures->sortBy(fn (YearlyFigure $figure) => $years[$figure->fiscal_year_id]);

            if ($figures->isEmpty()) {
                $writer->write([...$base, '', '', '', '', '', '']);
            }

            foreach ($figures as $figure) {
                $writer->write([...$base, $years[$figure->fiscal_year_id], ...array_map(fn (?int $value) => $value === null ? '' : CsvWriter::decimal($value), [
                    $figure->budget_cents, $figure->forecast_cents, $figure->run_bp, $figure->change_bp, $figure->fte_hundredths,
                ])]);
            }
        }
    }

    public function omitted(Organisation $organisation): int
    {
        return CostRecord::query()->where('organisation_id', $organisation->getKey())->whereNull('code')->count();
    }
}
