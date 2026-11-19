<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Services\Canvas\UnknownAllocationDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceDatasetFactory
{
    public function __construct(
        private int $elementCount = 500,
        private int $costRecordCount = 1000,
        private int $year = 2026,
        private int $seed = 20260101,
    ) {}

    /** @return array{organisation: Organisation, canvas: Canvas, year: int} */
    public function create(): array
    {
        mt_srand($this->seed);
        $areaId = Area::firstOrCreate(['key' => 'primary_activities'], ['kind' => 'activities', 'position' => 7, 'carries_costs' => true])->id;
        Area::firstOrCreate(['key' => 'supporting_activities'], ['kind' => 'activities', 'position' => 8, 'carries_costs' => true]);
        $organisation = Organisation::factory()->create();
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        UnknownAllocationDomain::ensure($canvas);
        $fiscalYear = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => $this->year]);
        $ownerId = DB::table('owners')->insertGetId(['organisation_id' => $organisation->id, 'name' => 'Owner', 'name_normalised' => 'owner', 'created_at' => now(), 'updated_at' => now()]);

        $targets = $this->elements($canvas, $areaId);
        $this->costRecords($organisation, $canvas, $fiscalYear, $ownerId, $targets);

        return ['organisation' => $organisation, 'canvas' => $canvas, 'year' => $this->year];
    }

    /** @return list<string> */
    private function elements(Canvas $canvas, int $areaId): array
    {
        $now = now();
        $rows = [];
        $targets = [];
        $row = fn (string $id, ?string $parent, string $kind, int $position) => [
            'id' => $id, 'canvas_id' => $canvas->id, 'area_id' => $areaId, 'parent_id' => $parent, 'kind' => $kind,
            'name' => "Element $id", 'name_normalised' => "element $id", 'position' => $position, 'is_unknown_allocation' => false,
            'created_at' => $now, 'updated_at' => $now,
        ];

        $parents = [];
        for ($d = 0; $d < 10; $d++) {
            $rows[] = $row($id = (string) Str::orderedUuid(), null, 'domain', $d);
            $targets[] = $id;
            for ($c = 0; $c < 4; $c++) {
                $rows[] = $row($cluster = (string) Str::orderedUuid(), $id, 'cluster', $c);
                $targets[] = $cluster;
                $parents[] = $cluster;
            }
        }
        for ($a = 0; count($rows) < $this->elementCount - 1; $a++) {
            $rows[] = $row($id = (string) Str::orderedUuid(), $parents[$a % count($parents)], 'activity', $a);
            $targets[] = $id;
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('elements')->insert($chunk);
        }

        return $targets;
    }

    /** @param list<string> $targets */
    private function costRecords(Organisation $organisation, Canvas $canvas, FiscalYear $fiscalYear, int $ownerId, array $targets): void
    {
        $now = now();
        $kinds = ['supplier_contract', 'personnel', 'project', 'other'];
        $records = [];
        $figures = [];
        $sets = [];
        $lines = [];
        for ($i = 0; $i < $this->costRecordCount; $i++) {
            $id = (string) Str::orderedUuid();
            $records[] = ['id' => $id, 'organisation_id' => $organisation->id, 'name' => "Cost record $i", 'cost_kind' => $kinds[$i % 4], 'owner_id' => $ownerId, 'created_at' => $now, 'updated_at' => $now];
            $run = mt_rand(0, 6000);
            $figures[] = [
                'cost_record_id' => $id, 'fiscal_year_id' => $fiscalYear->id, 'budget_cents' => mt_rand(0, 99_999_999_999),
                'forecast_cents' => $i % 3 === 0 ? null : mt_rand(0, 99_999_999_999), 'run_bp' => $run, 'change_bp' => mt_rand(0, 10000 - $run),
                'fte_hundredths' => mt_rand(0, 500), 'created_at' => $now, 'updated_at' => $now,
            ];
            if ($i % 10 === 9) {
                continue;
            }
            $setId = $i + 1;
            $sets[] = ['id' => $setId, 'canvas_id' => $canvas->id, 'cost_record_id' => $id, 'fiscal_year_id' => $fiscalYear->id, 'created_at' => $now, 'updated_at' => $now];
            $count = mt_rand(1, 5);
            foreach ((array) array_rand($targets, $count) as $position => $targetIndex) {
                $lines[] = ['allocation_set_id' => $setId, 'element_id' => $targets[$targetIndex], 'basis_points' => intdiv(10000, $count) + ($position === 0 ? 10000 % $count : 0), 'position' => $position];
            }
        }

        foreach ([['cost_records', $records], ['yearly_figures', $figures], ['allocation_sets', $sets], ['allocation_lines', $lines]] as [$table, $rows]) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }
}
