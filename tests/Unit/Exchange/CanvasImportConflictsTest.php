<?php

namespace Tests\Unit\Exchange;

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Services\Exchange\CanvasImportValidator;
use Tests\Feature\Exchange\CanvasExchangeTestCase;

class CanvasImportConflictsTest extends CanvasExchangeTestCase
{
    /** @return array{errors: list<array{location: string, message: string}>, reused: list<string>} */
    private function check(?Organisation $target, string $canvasName = 'Nieuw canvas', ?string $file = null): array
    {
        $result = app(CanvasImportValidator::class)->check($file ?? (string) json_encode($this->exported()), $target, $canvasName);

        return ['errors' => $result['errors'], 'reused' => $result['reused']];
    }

    private function emptyTarget(): Organisation
    {
        $target = Organisation::factory()->create();

        foreach ([2026, 2027] as $year) {
            FiscalYear::create(['organisation_id' => $target->id, 'year' => $year]);
        }

        return $target;
    }

    public function test_equal_cost_records_of_the_target_are_reused(): void
    {
        $this->richCanvas();

        $result = $this->check($this->canvas->organisation);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['reused']);
    }

    public function test_a_cost_record_that_differs_from_the_existing_one_is_reported(): void
    {
        $this->richCanvas();
        $file = (string) json_encode($this->exported());
        $this->costRecordByCode('LC-001')->update(['name' => 'Anders']);

        $errors = $this->check($this->canvas->organisation, file: $file)['errors'];

        $this->assertCount(1, $errors);
        $this->assertSame('Kostenpost LC-001 verschilt van de bestaande', $errors[0]['message']);
        $this->assertStringStartsWith('cost_records[', $errors[0]['location']);
    }

    public function test_different_yearly_figures_make_a_cost_record_differ(): void
    {
        $this->richCanvas();
        $file = (string) json_encode($this->exported());
        $this->costRecordByCode('LC-002')->yearlyFigures()->first()?->update(['budget_cents' => 1]);

        $this->assertSame('Kostenpost LC-002 verschilt van de bestaande', $this->check($this->canvas->organisation, file: $file)['errors'][0]['message']);
    }

    public function test_a_code_held_by_a_different_cost_record_is_an_error(): void
    {
        $this->richCanvas();
        $target = $this->emptyTarget();
        CostRecord::factory()->create(['organisation_id' => $target->id, 'code' => 'lc-001']);

        $errors = $this->check($target)['errors'];

        $this->assertCount(1, $errors);
        $this->assertMatchesRegularExpression('/^cost_records\[\d+\]\.code$/', $errors[0]['location']);
    }

    public function test_figures_for_a_year_finalised_in_a_canvas_of_the_target_are_an_error(): void
    {
        $this->richCanvas();
        $target = $this->emptyTarget();
        $other = Canvas::factory()->create(['organisation_id' => $target->id, 'name' => 'Ander']);
        FinalisedYear::create(['canvas_id' => $other->id, 'fiscal_year_id' => FiscalYear::where('organisation_id', $target->id)->where('year', 2027)->value('id'), 'finalised_at' => now()]);

        $errors = $this->check($target)['errors'];

        $this->assertCount(2, $errors);
        $this->assertMatchesRegularExpression('/^cost_records\[\d+\]\.yearly_figures\[1\]\.year$/', $errors[0]['location']);
        $this->assertStringContainsString('2027', $errors[0]['message']);
    }

    public function test_a_canvas_name_taken_in_the_target_is_an_error(): void
    {
        $this->richCanvas();

        $errors = $this->check($this->canvas->organisation, 'Basis canvas')['errors'];

        $this->assertSame('canvas_name', $errors[0]['location']);
    }

    public function test_an_empty_target_gives_no_conflicts(): void
    {
        $this->richCanvas();

        $this->assertSame([], $this->check($this->emptyTarget())['errors']);
    }
}
