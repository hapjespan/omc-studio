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

        return match ($group) {
            'cost_record' => [
                ['K-100', 'Hosting', $contract, 'Voorbeeld Hosting BV', 'A. Jansen', 'CC-10', 'Klantportaal', '2026', '12000,00', '13000,50', '80', '20', '0,5'],
                ['K-101', 'Migratieproject', $project, '', 'B. de Vries', '', '', '2026', '45000,00', '', '', '', '1,5'],
                ['K-102', 'Licenties', $contract, 'Voorbeeld Software BV', 'A. Jansen', 'CC-11', '', '', '', '', '', '', ''],
            ],
            'structure' => [
                [$area, 'Inkoop', '', '', 'Alles rond het inkopen van diensten', ''],
                [$area, 'Klantcontact', 'Service', 'Klachtenafhandeling', '', 'common'],
                [$area, 'Klantcontact', 'Service', 'Vragen beantwoorden', '', 'adaptive'],
            ],
            default => [
                ['K-100', '2026', $area.' > Inkoop', '60'],
                ['K-100', '2026', $area.' > Klantcontact', '40'],
                ['K-101', '2026', $area.' > Inkoop', '100'],
            ],
        };
    }
}
