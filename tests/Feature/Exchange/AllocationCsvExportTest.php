<?php

namespace Tests\Feature\Exchange;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\CostRecord;

class AllocationCsvExportTest extends ExchangeTestCase
{
    private function set(CostRecord $record, array $lines): void
    {
        $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id]);

        foreach ($lines as $position => [$element, $basisPoints]) {
            AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $element->id, 'basis_points' => $basisPoints, 'position' => $position]);
        }
    }

    private function export(): string
    {
        return $this->get('/canvases/'.$this->canvas->id.'/export/allocations.csv')->assertOk()->streamedContent();
    }

    public function test_one_line_per_allocation_line_in_set_order_with_display_name_paths(): void
    {
        $cluster = $this->element('primary_activities', ElementKind::Cluster, 'Contracten', $this->domain);
        $deep = $this->element('primary_activities', ElementKind::Activity, 'Tekenen', $cluster);
        $this->set($this->costRecord('K1'), [[$this->activity, 3333], [$deep, 6667]]);

        $this->assertSame(
            "kostenpost_code;jaar;doel_pad;percentage\nK1;2026;Primaire activiteiten > Inkoop > Offreren;33,33\nK1;2026;Primaire activiteiten > Inkoop > Contracten > Tekenen;66,67\n",
            $this->export(),
        );
    }

    public function test_records_without_code_are_left_out_and_counted(): void
    {
        $this->set($this->costRecord('K1'), [[$this->activity, 10_000]]);
        $this->set($this->costRecord(null, 'Zonder code'), [[$this->activity, 10_000]]);
        $this->costRecord(null, 'Zonder verdeling');

        $response = $this->get('/canvases/'.$this->canvas->id.'/export/allocations.csv')->assertOk();

        $response->assertHeader('X-Omitted-Cost-Records', '1');
        $this->assertCount(2, explode("\n", trim($response->streamedContent())));
    }

    public function test_an_export_with_no_sets_holds_only_the_header(): void
    {
        $this->assertSame("kostenpost_code;jaar;doel_pad;percentage\n", $this->export());
    }
}
