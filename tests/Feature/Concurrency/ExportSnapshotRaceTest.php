<?php

namespace Tests\Feature\Concurrency;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\YearlyFigure;
use App\Services\Exchange\CanvasExport;
use App\Services\Exchange\FullExport;
use App\Services\Structure\ElementTree;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ExportSnapshotRaceTest extends RaceTestCase
{
    public function test_single_canvas_export_shows_a_concurrent_year_and_figure_edit_complete_or_not_at_all(): void
    {
        $data = $this->exportWhileWriting(fn () => app(CanvasExport::class)->build($this->canvas));

        $this->assertConsistent($data['organisation']['fiscal_years'], $data['cost_records'], $data['canvas']);
    }

    public function test_full_export_shows_a_concurrent_year_and_figure_edit_complete_or_not_at_all(): void
    {
        $data = $this->exportWhileWriting(fn () => app(FullExport::class)->build());

        $organisation = $data['organisations'][0];

        $this->assertConsistent($organisation['organisation']['fiscal_years'], $organisation['cost_records'], $organisation['canvases'][0]);
    }

    public function test_export_names_only_existing_targets_when_allocations_change_concurrently(): void
    {
        $data = $this->exportWhileWriting(fn () => app(CanvasExport::class)->build($this->canvas));

        $ids = array_column($data['canvas']['elements'], 'id');

        foreach ($data['canvas']['allocation_sets'] as $set) {
            foreach ($set['lines'] as $line) {
                $this->assertContains($line['element_id'], $ids);
            }
        }
    }

    protected function exportWhileWriting(Closure $export): array
    {
        $writer = $this->writer();
        $fired = [];

        DB::listen(function ($query) use ($writer, &$fired): void {
            if ($query->connectionName !== 'race') {
                return;
            }

            foreach ([
                'fiscal_years' => fn () => $this->addYearWithFigure($writer),
                'elements' => fn () => $this->moveAllocation($writer),
            ] as $table => $write) {
                if (! isset($fired[$table]) && str_contains($query->sql, 'from "'.$table.'"')) {
                    $fired[$table] = true;
                    $write();
                }
            }
        });

        $result = null;

        $this->onRace(function () use ($export, &$result): void {
            $result = $export();
        });

        $this->assertCount(2, $fired);

        return $result;
    }

    private function writer(): Connection
    {
        config(['database.connections.writer' => config('database.connections.race')]);

        return DB::connection('writer');
    }

    private function addYearWithFigure(Connection $writer): void
    {
        Model::withoutEvents(function (): void {
            $year = FiscalYear::on('writer')->create(['organisation_id' => $this->canvas->organisation_id, 'year' => 2027]);
            YearlyFigure::on('writer')->create(['cost_record_id' => $this->unallocated->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 5_000, 'forecast_cents' => 0, 'run_bp' => 0, 'change_bp' => 0, 'fte_hundredths' => 0]);
        });
    }

    private function moveAllocation(Connection $writer): void
    {
        Model::withoutEvents(fn () => $writer->transaction(function (): void {
            $activity = Element::on('writer')->where('canvas_id', $this->canvas->id)->where('kind', ElementKind::Activity)->firstOrFail();
            $replacement = Element::on('writer')->create(['canvas_id' => $this->canvas->id, 'area_id' => $activity->area_id, 'parent_id' => $activity->parent_id, 'kind' => ElementKind::Activity, 'name' => 'Nieuw', 'position' => 9]);
            AllocationLine::on('writer')->where('element_id', $activity->id)->update(['element_id' => $replacement->id]);
            Element::on('writer')->whereKey($activity->id)->update(['archived_at' => now()]);
        }));
    }

    /**
     * @param  list<int>  $years
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $canvas
     */
    private function assertConsistent(array $years, array $records, array $canvas): void
    {
        foreach ($records as $record) {
            foreach ($record['yearly_figures'] as $figure) {
                $this->assertContains($figure['year'], $years);
            }
        }

        $ids = array_column($canvas['elements'], 'id');

        foreach ($canvas['allocation_sets'] as $set) {
            foreach ($set['lines'] as $line) {
                $this->assertContains($line['element_id'], $ids);
            }
        }
    }
}
