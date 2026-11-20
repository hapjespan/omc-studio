<?php

namespace App\Services\Finance;

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Services\Lists\ListQuery;
use App\Support\Format;
use Illuminate\Support\Facades\DB;

class CostRecordList
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{rows: list<array<string, mixed>>, count: int, query: array<string, mixed>}
     */
    public function run(Organisation $organisation, ?FiscalYear $year, array $input, ?Canvas $canvas = null): array
    {
        $yearId = $year?->getKey();
        $canvasFilter = $canvas === null ? '' : ' AND canvas_id = '.(int) $canvas->getKey();

        $inner = CostRecord::query()
            ->from('cost_records')
            ->leftJoin('yearly_figures', fn ($join) => $join->on('yearly_figures.cost_record_id', '=', 'cost_records.id')->where('yearly_figures.fiscal_year_id', '=', $yearId))
            ->where('cost_records.organisation_id', $organisation->getKey())
            ->select([
                'cost_records.id', 'cost_records.code', 'cost_records.name', 'cost_records.cost_kind', 'cost_records.supplier', 'cost_records.owner_id',
                'cost_records.cost_centre', 'cost_records.application_id', 'cost_records.description', 'cost_records.archived_at',
                'yearly_figures.budget_cents', 'yearly_figures.forecast_cents', 'yearly_figures.run_bp', 'yearly_figures.change_bp',
                'yearly_figures.fte_hundredths', 'yearly_figures.reference_date', 'yearly_figures.remark',
                DB::raw('(SELECT COUNT(*) FROM yearly_figures AS yf WHERE yf.cost_record_id = cost_records.id) AS figure_count'),
                DB::raw('(SELECT COUNT(*) FROM allocation_sets AS asets WHERE asets.cost_record_id = cost_records.id) AS set_count'),
                DB::raw('(SELECT COUNT(*) FROM allocation_sets AS ysets WHERE ysets.cost_record_id = cost_records.id AND ysets.fiscal_year_id = '.(int) $yearId.str_replace('canvas_id', 'ysets.canvas_id', $canvasFilter).') AS year_set_count'),
                DB::raw('CASE WHEN EXISTS (SELECT 1 FROM allocation_sets WHERE allocation_sets.cost_record_id = cost_records.id AND allocation_sets.fiscal_year_id = '.(int) $yearId.str_replace('canvas_id', 'allocation_sets.canvas_id', $canvasFilter).") THEN 'allocated' ELSE 'unallocated' END AS allocation"),
            ]);

        $list = (new ListQuery(['code', 'name'], ['code', 'name'], ['cost_kind' => 'cost_kind', 'owner_id' => 'owner_id', 'application_id' => 'application_id', 'allocation' => 'allocation'], 'archived_at'))
            ->run(CostRecord::query()->fromSub($inner, 'cost_records')->orderByRaw('LOWER(name)'), $input);

        $list['rows'] = array_map(fn (array $row) => $this->present($row), $list['rows']);

        return $list;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $amount = fn (?int $cents) => $cents === null ? null : Format::formatAmount($cents);
        $input = fn (?int $cents) => $cents === null ? '' : str_replace('€ ', '', Format::formatAmount($cents));
        $share = fn (?int $points) => $points === null ? '' : rtrim(rtrim(sprintf('%d,%02d', intdiv($points, 100), $points % 100), '0'), ',');

        return $row + [
            'budget' => $amount($row['budget_cents']),
            'forecast' => $amount($row['forecast_cents']),
            'has_figures' => $row['budget_cents'] !== null,
            'figures' => [
                'budget' => $input($row['budget_cents']),
                'forecast' => $input($row['forecast_cents']),
                'run' => $share($row['run_bp']),
                'change' => $share($row['change_bp']),
                'fte' => $row['fte_hundredths'] === null ? '' : Format::formatFte($row['fte_hundredths']),
                'reference_date' => (string) $row['reference_date'],
                'remark' => (string) $row['remark'],
            ],
        ];
    }
}
