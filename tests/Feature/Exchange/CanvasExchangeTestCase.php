<?php

namespace Tests\Feature\Exchange;

use App\Enums\StatementKind;
use App\Models\Application;
use App\Models\CostRecord;
use App\Models\Modality;
use App\Models\Owner;
use App\Models\Statement;
use App\Models\YearlyFigure;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Exchange\CanvasExport;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Allocation\AllocationTestCase;

abstract class CanvasExchangeTestCase extends AllocationTestCase
{
    protected function richCanvas(): void
    {
        $organisation = $this->canvas->organisation;
        $organisation->update(['name' => 'Acme Zorg', 'slug' => 'acme-zorg']);
        $this->canvas->update(['name' => 'Basis canvas']);

        Statement::create(['canvas_id' => $this->canvas->id, 'kind' => StatementKind::Mission, 'title' => null, 'body' => 'Zorg dichtbij', 'position' => 0]);
        Statement::create(['canvas_id' => $this->canvas->id, 'kind' => StatementKind::Goal, 'title' => 'Groei', 'body' => 'Meer bereik', 'position' => 1]);

        $domain = $this->domain('primary_activities', 'Inkoop');
        $cluster = $this->cluster($domain, 'Werving');
        $first = $this->activity($cluster, 'Offreren');
        $second = $this->activity($domain, 'Bestellen');
        $second->update(['modality_id' => Modality::where('key', 'common')->value('id'), 'rationale' => 'Standaard']);
        $archived = $this->activity($domain, 'Oud');
        $archived->update(['archived_at' => now()]);
        $support = $this->domain('supporting_activities', 'Facilitair');
        $third = $this->activity($support, 'Schoonmaak');
        UnknownAllocationDomain::ensure($this->canvas);

        $application = Application::factory()->create(['organisation_id' => $organisation->id, 'name' => 'ERP', 'supplier' => 'Leverancier X']);
        $owner = Owner::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Jansen']);

        foreach ([['LC-001', 1_250_000, 'Licenties'], ['LC-002', 333_333, 'Onderhoud']] as [$code, $budget, $name]) {
            $record = $this->costRecord($name, 2026, $budget);
            $record->update(['code' => $code, 'owner_id' => $owner->id, 'application_id' => $application->id, 'supplier' => 'Acme', 'cost_centre' => 'K1']);
            YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->fiscalYear(2027)->id, 'budget_cents' => $budget + 1, 'forecast_cents' => $budget + 7, 'run_bp' => 6000, 'change_bp' => 4000, 'fte_hundredths' => 150]);
            $this->allocate($record, 2026, [$first->id => 3333, $second->id => 3333, $third->id => 3334]);
        }

        $this->allocate($this->costRecordByCode('LC-001'), 2027, [$third->id => 10000]);
        $this->allocate($this->costRecordByCode('LC-002'), 2027, [$third->id => 10000]);
        $this->finalise(2027);
    }

    protected function wipe(): void
    {
        foreach (['layouts', 'allocation_lines', 'allocation_sets', 'snapshots', 'yearly_figures', 'finalised_years', 'elements', 'statements', 'cost_records', 'owners', 'applications', 'fiscal_years'] as $table) {
            DB::table($table)->delete();
        }

        DB::table('canvases')->where('is_blank_template', false)->delete();
        DB::table('organisations')->delete();
    }

    protected function costRecordByCode(string $code): CostRecord
    {
        return CostRecord::where('code_normalised', strtolower($code))->firstOrFail();
    }

    /** @return array<string, mixed> */
    protected function exported(): array
    {
        return app(CanvasExport::class)->build($this->canvas->fresh());
    }
}
