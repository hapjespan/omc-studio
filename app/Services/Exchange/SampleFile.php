<?php

namespace App\Services\Exchange;

use App\Services\Exchange\Csv\CsvWriter;
use InvalidArgumentException;

class SampleFile
{
    public const GROUPS = ['cost_record', 'structure', 'allocation'];

    public function write(string $group, mixed $handle): void
    {
        $columns = match ($group) {
            'cost_record' => CostRecordImport::COLUMNS,
            'structure' => StructureCsv::COLUMNS,
            'allocation' => AllocationCsv::COLUMNS,
            default => throw new InvalidArgumentException('Unknown sample group '.$group),
        };

        $writer = new CsvWriter($handle);
        $writer->write(Headers::row($group, $columns));

        foreach ($this->rows($group) as $row) {
            $writer->write($row);
        }
    }

    /**
     * @return list<list<string>>
     */
    private function rows(string $group): array
    {
        $area = (string) __('areas.primary_activities.name');
        $project = Headers::label('cost_kind', 'project');
        $contract = Headers::label('cost_kind', 'supplier_contract');

        return array_map(
            fn (int $n): array => explode(';', (string) __('exchange.sample_row.'.$group.'.'.$n, ['area' => $area, 'project' => $project, 'contract' => $contract])),
            range(1, 3),
        );
    }
}
