<?php

namespace Tests\Feature\Concurrency;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Snapshot;
use App\Models\YearlyFigure;
use App\Services\Exchange\CanvasExport;
use App\Services\Exchange\CanvasImport;
use App\Services\Exchange\FullExport;
use App\Services\Exchange\FullImport;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ExportSnapshotRaceTest extends RaceTestCase
{
    public function test_single_canvas_export_shows_a_concurrent_year_and_figure_edit_complete_or_not_at_all(): void
    {
        $data = $this->exportWhileWriting(fn () => app(CanvasExport::class)->build($this->canvas), ['fiscal_years']);

        $this->assertConsistent($data['organisation']['fiscal_years'], $data['cost_records'], $data['canvas']);
    }

    public function test_full_export_shows_a_concurrent_year_and_figure_edit_complete_or_not_at_all(): void
    {
        $data = $this->exportWhileWriting(fn () => app(FullExport::class)->build(), ['fiscal_years']);

        $organisation = $data['organisations'][0];

        $this->assertConsistent($organisation['organisation']['fiscal_years'], $organisation['cost_records'], $organisation['canvases'][0]);
    }

    public function test_export_names_only_existing_targets_when_allocations_change_concurrently(): void
    {
        $data = $this->exportWhileWriting(fn () => app(CanvasExport::class)->build($this->canvas), ['elements']);

        $ids = array_column($data['canvas']['elements'], 'id');

        foreach ($data['canvas']['allocation_sets'] as $set) {
            foreach ($set['lines'] as $line) {
                $this->assertContains($line['element_id'], $ids);
            }
        }
    }

    public function test_full_export_with_a_damaged_snapshot_names_it_and_keeps_the_notice(): void
    {
        $this->onRace(fn () => Model::withoutEvents(fn () => Snapshot::create([
            'canvas_id' => $this->canvas->id, 'name' => 'Stuk', 'fiscal_year' => self::YEAR, 'created_at' => now(),
            'schema_version' => 1, 'content' => '{"schema_version":1}', 'checksum' => 'wrong',
        ])));

        $export = app(FullExport::class);
        $data = null;
        $this->onRace(function () use ($export, &$data): void {
            $data = $export->build();
        });

        $this->assertSame(1, $export->omittedSnapshots);
        $this->assertSame([], $data['organisations'][0]['canvases'][0]['snapshots']);
        $this->assertSame(1, DB::connection('race')->table('system_notices')->where('kind', 'snapshot_damaged')->count());
    }

    public function test_both_downloads_made_during_the_race_import_with_their_exported_totals(): void
    {
        $canvas = $this->exportWhileWriting(fn () => app(CanvasExport::class)->build($this->canvas), ['fiscal_years', 'elements']);
        $full = $this->exportWhileWriting(fn () => app(FullExport::class)->build(), ['fiscal_years', 'elements']);

        $this->onRace(function () use ($canvas): void {
            $result = app(CanvasImport::class)->commit((string) json_encode($canvas), null, 'Teruggezet', 'Importorganisatie');

            $this->assertSame($this->summary($canvas), $this->summary($this->exported(fn () => app(CanvasExport::class)->build(Canvas::findOrFail($result['canvas_id'])))));
            $this->assertSame(count($canvas['cost_records']), $result['totals']['cost_records']);
        });

        $this->emptyOrganisations();

        $this->onRace(function () use ($full): void {
            $result = app(FullImport::class)->commit((string) json_encode($full));
            $again = $this->exported(fn () => app(FullExport::class)->build());

            $this->assertSame($full['organisations'][0]['canvases'][0]['name'], $again['organisations'][0]['canvases'][0]['name']);
            $this->assertSame($this->summary($this->shaped($full)), $this->summary($this->shaped($again)));
            $this->assertSame(1, $result['totals']['organisations']);
            $this->assertSame(count($full['organisations'][0]['cost_records']), $result['totals']['cost_records']);
        });
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    /** @return array<string, mixed> */
    private function exported(Closure $export): array
    {
        return $export();
    }

    /**
     * @param  array<string, mixed>  $full
     * @return array<string, mixed>
     */
    private function shaped(array $full): array
    {
        $entry = $full['organisations'][0];

        return ['organisation' => $entry['organisation'], 'cost_records' => $entry['cost_records'], 'canvas' => $entry['canvases'][0]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function summary(array $data): array
    {
        $sets = $data['canvas']['allocation_sets'];
        $elements = array_filter($data['canvas']['elements'], fn (array $element) => ! ($element['is_unknown_allocation'] ?? false));

        return [
            'years' => $data['organisation']['fiscal_years'],
            'records' => self::sorted(array_column($data['cost_records'], 'name')),
            'budget' => array_sum(array_map(fn (array $record) => array_sum(array_column($record['yearly_figures'], 'budget_cents')), $data['cost_records'])),
            'elements' => count($elements),
            'sets' => count($sets),
            'basis_points' => array_sum(array_map(fn (array $set) => array_sum(array_column($set['lines'], 'basis_points')), $sets)),
        ];
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, mixed>
     */
    protected function exportWhileWriting(Closure $export, array $tables): array
    {
        $writer = $this->writer();
        $fired = [];

        DB::listen(function ($query) use ($writer, $tables, &$fired): void {
            if ($query->connectionName !== 'race') {
                return;
            }

            foreach (array_intersect_key([
                'fiscal_years' => fn () => $this->addYearWithFigure($writer),
                'elements' => fn () => $this->moveAllocation($writer),
            ], array_flip($tables)) as $table => $write) {
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

        $this->assertCount(count($tables), $fired);

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
            $year = FiscalYear::on('writer')->create(['organisation_id' => $this->canvas->organisation_id, 'year' => FiscalYear::on('writer')->max('year') + 1]);
            YearlyFigure::on('writer')->create(['cost_record_id' => $this->unallocated->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 5_000, 'forecast_cents' => 0, 'run_bp' => 0, 'change_bp' => 0, 'fte_hundredths' => 0]);
        });
    }

    private function moveAllocation(Connection $writer): void
    {
        Model::withoutEvents(fn () => $writer->transaction(function (): void {
            $activity = Element::on('writer')->where('canvas_id', $this->canvas->id)->where('kind', ElementKind::Activity)->whereNull('archived_at')->firstOrFail();
            $replacement = (new Element)->setConnection('writer')->forceFill(['canvas_id' => $this->canvas->id, 'area_id' => $activity->area_id, 'parent_id' => $activity->parent_id, 'kind' => ElementKind::Activity, 'name' => 'Nieuw', 'name_normalised' => 'nieuw', 'position' => 9]);
            $replacement->save();
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
