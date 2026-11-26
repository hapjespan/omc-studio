<?php

namespace Tests\Feature\Exchange;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Allocation\AllocationSets;
use App\Services\Allocation\FinalisedYears;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use App\Services\Support\SerialisedWrite;
use App\Services\Undo\UndoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

class AllocationCsvImportTest extends ExchangeTestCase
{
    private const HEADER = 'kostenpost_code;jaar;doel_pad;percentage';

    private const OFFREREN = 'Primaire activiteiten > Inkoop > Offreren';

    private const UNKNOWN = 'Ondersteunende activiteiten > Onbekende allocatie';

    /**
     * @param  list<string>  $lines
     * @return TestResponse<JsonResponse>
     */
    private function preview(array $lines): TestResponse
    {
        return $this->upload(self::HEADER, $lines, '/canvases/'.$this->canvas->id.'/import/allocations/preview');
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<JsonResponse>
     */
    private function commit(array $lines): TestResponse
    {
        return $this->upload(self::HEADER, $lines, '/canvases/'.$this->canvas->id.'/import/allocations');
    }

    /**
     * @param  TestResponse<JsonResponse>  $response
     * @return list<string>
     */
    private function messages(TestResponse $response, int $index = 0): array
    {
        return $response->json("lines.$index.messages");
    }

    /**
     * @param  list<array{0: Element, 1: int}>  $lines
     */
    private function set(CostRecord $record, array $lines, ?FiscalYear $year = null): AllocationSet
    {
        $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $record->id, 'fiscal_year_id' => ($year ?? $this->year)->id]);

        foreach ($lines as $position => [$element, $basisPoints]) {
            AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $element->id, 'basis_points' => $basisPoints, 'position' => $position]);
        }

        return $set;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function stored(CostRecord $record): array
    {
        $set = AllocationSet::where('canvas_id', $this->canvas->id)->where('cost_record_id', $record->id)->firstOrFail();

        return $set->lines()->get()->map(fn (AllocationLine $line) => [(string) $line->element_id, $line->basis_points])->all();
    }

    private function second(): Element
    {
        return $this->element('primary_activities', ElementKind::Activity, 'Bestellen', $this->domain);
    }

    public function test_an_import_records_its_set_changes_under_the_action_imported(): void
    {
        $updated = $this->costRecord('K2');
        $this->set($updated, [[$this->activity, 10_000]]);
        $this->costRecord('K3');
        AuditEntry::query()->delete();

        $this->commit(['K2;2026;'.self::OFFREREN.';100,00', 'K3;2026;'.self::OFFREREN.';100,00'])->assertOk();

        $actions = AuditEntry::where('subject_type', 'allocation_set')->pluck('action')->unique()->all();

        $this->assertSame(['imported'], $actions);
    }

    public function test_lines_are_grouped_per_code_and_year_into_sets_with_a_status_and_totals(): void
    {
        $this->second();
        $unchanged = $this->costRecord('K1');
        $this->set($unchanged, [[$this->activity, 10_000]]);
        $updated = $this->costRecord('K2');
        $this->set($updated, [[$this->activity, 10_000]]);
        $this->costRecord('K3');

        $response = $this->preview([
            'K1;2026;'.self::OFFREREN.';100,00',
            'K2;2026;'.self::OFFREREN.';40,00',
            'K2;2026;Primaire activiteiten > Inkoop > Bestellen;60,00',
            'k3;2026;'.self::OFFREREN.';100,00',
        ])->assertOk();

        $response->assertJsonPath('lines.0.status', 'unchanged')
            ->assertJsonPath('lines.1.status', 'updated')
            ->assertJsonPath('lines.2.status', 'updated')
            ->assertJsonPath('lines.3.status', 'new')
            ->assertJsonPath('totals', ['new' => 1, 'updated' => 2, 'unchanged' => 1, 'errors' => 0]);
        $this->assertSame([[(string) $this->activity->id, 10_000]], $this->stored($updated));
    }

    public function test_an_unknown_code_year_path_non_target_or_percentage_is_reported_on_its_line(): void
    {
        $this->costRecord('K1');

        $response = $this->preview([
            'X9;2026;'.self::OFFREREN.';100,00',
            'K1;2030;'.self::OFFREREN.';100,00',
            'K1;2026;Primaire activiteiten > Inkoop > Bestaat niet;100,00',
            'K1;2026;'.self::UNKNOWN.';100,00',
            'K1;2026;'.self::OFFREREN.';honderd',
        ])->assertOk();

        $this->assertSame(["Regel 2, kostenpost_code: 'X9' is geen kostenpost van deze organisatie"], $this->messages($response, 0));
        $this->assertSame(["Regel 3, jaar: '2030' is geen boekjaar van deze organisatie. Voeg het boekjaar eerst toe"], $this->messages($response, 1));
        $this->assertSame(["Regel 4, doel_pad: 'Primaire activiteiten > Inkoop > Bestaat niet' is geen doel in dit canvas"], $this->messages($response, 2));
        $this->assertSame(["Regel 5, doel_pad: '".self::UNKNOWN."' kan geen kosten dragen"], $this->messages($response, 3));
        $this->assertStringStartsWith("Regel 6, percentage: 'honderd' is geen percentage", $this->messages($response, 4)[0]);
        $response->assertJsonPath('totals.errors', 5)->assertJsonPath('lines.0.status', 'error');
    }

    public function test_an_archived_target_is_an_unknown_path(): void
    {
        $this->costRecord('K1');
        $this->activity->update(['archived_at' => now()]);

        $response = $this->preview(['K1;2026;'.self::OFFREREN.';100,00'])->assertOk();

        $this->assertSame(["Regel 2, doel_pad: '".self::OFFREREN."' is geen doel in dit canvas"], $this->messages($response));
    }

    public function test_a_sum_other_than_a_hundred_a_repeated_target_and_missing_figures_are_reported_on_the_first_line_of_the_group(): void
    {
        $this->second();
        $this->costRecord('K1');
        $this->costRecord('K2');
        $this->costRecord('K3', 'Zonder cijfers', null);

        $response = $this->preview([
            'K1;2026;'.self::OFFREREN.';50,00',
            'K1;2026;Primaire activiteiten > Inkoop > Bestellen;25,00',
            'K2;2026;'.self::OFFREREN.';50,00',
            'K2;2026;'.self::OFFREREN.';50,00',
            'K3;2026;'.self::OFFREREN.';100,00',
        ])->assertOk();

        $this->assertSame(['De verdeling moet precies 100,00% zijn; nu 75,00%'], $this->messages($response, 0));
        $this->assertSame([], $this->messages($response, 1));
        $this->assertSame('error', $response->json('lines.1.status'));
        $this->assertSame(['Doel Offreren staat twee keer in de verdeling'], $this->messages($response, 2));
        $this->assertSame(['Geen jaarwaarden in 2026 voor deze kostenpost'], $this->messages($response, 4));
        $response->assertJsonPath('totals.errors', 5);
    }

    public function test_a_year_finalised_in_this_canvas_is_reported_per_group(): void
    {
        $this->costRecord('K1');
        FinalisedYear::factory()->create(['canvas_id' => $this->canvas->id, 'fiscal_year_id' => $this->year->id]);

        $response = $this->preview(['K1;2026;'.self::OFFREREN.';100,00'])->assertOk();

        $this->assertSame(['2026 is definitief. Heropen het jaar eerst'], $this->messages($response));
    }

    public function test_an_archived_canvas_is_reported_per_group(): void
    {
        $this->costRecord('K1');
        $this->canvas->update(['archived_at' => now()]);

        $response = $this->preview(['K1;2026;'.self::OFFREREN.';100,00'])->assertOk();

        $this->assertSame(['Dit canvas is gearchiveerd'], $this->messages($response));
    }

    public function test_a_commit_with_one_sound_and_one_faulty_group_writes_nothing(): void
    {
        $this->costRecord('K1');
        $this->costRecord('K2');

        $response = $this->commit([
            'K1;2026;'.self::OFFREREN.';100,00',
            'K2;2026;'.self::OFFREREN.';90,00',
        ])->assertStatus(422);

        $response->assertJsonPath('errors.file.0', 'Importeren is niet gelukt; het bestand bevat fouten. Er is niets gewijzigd');
        $this->assertSame(0, AllocationSet::count());
        $this->assertSame(0, AllocationLine::count());
    }

    public function test_a_commit_replaces_the_existing_set_and_keeps_the_order_of_the_file(): void
    {
        $bestellen = $this->second();
        $record = $this->costRecord('K1');
        $this->set($record, [[$this->activity, 10_000]]);

        $this->commit([
            'K1;2026;Primaire activiteiten > Inkoop > Bestellen;60,00',
            'K1;2026;'.self::OFFREREN.';40,00',
        ])->assertOk()->assertJsonPath('totals', ['new' => 0, 'updated' => 2, 'unchanged' => 0, 'errors' => 0]);

        $this->assertSame([[(string) $bestellen->id, 6_000], [(string) $this->activity->id, 4_000]], $this->stored($record));
        $this->assertSame(1, AllocationSet::count());
    }

    public function test_a_failure_halfway_rolls_back_the_groups_already_written(): void
    {
        $this->costRecord('K1');
        $this->costRecord('K2');
        $this->app->instance(AllocationSets::class, new class(app(SerialisedWrite::class), app(FinalisedYears::class), app(AuditTrail::class), app(UndoStore::class), app(AuditContext::class)) extends AllocationSets
        {
            private int $calls = 0;

            public function replace(Canvas $canvas, CostRecord $record, FiscalYear $year, array $lines): AllocationSet
            {
                $set = parent::replace($canvas, $record, $year, $lines);

                if (++$this->calls === 2) {
                    throw ValidationException::withMessages(['rule' => 'halted']);
                }

                return $set;
            }
        });

        $this->commit([
            'K1;2026;'.self::OFFREREN.';100,00',
            'K2;2026;'.self::OFFREREN.';100,00',
        ])->assertStatus(422)->assertJsonPath('errors.rule.0', 'halted');

        $this->assertSame(0, AllocationSet::count());
        $this->assertSame(0, AllocationLine::count());
    }

    public function test_the_export_read_back_reads_unchanged_on_every_line(): void
    {
        $bestellen = $this->second();
        $record = $this->costRecord('K1');
        $this->set($record, [[$this->activity, 3_333], [$bestellen, 6_667]]);

        $export = $this->get('/canvases/'.$this->canvas->id.'/export/allocations.csv')->assertOk()->streamedContent();
        $file = UploadedFile::fake()->createWithContent('allocations.csv', $export);

        $this->post('/canvases/'.$this->canvas->id.'/import/allocations/preview', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('lines.0.status', 'unchanged')
            ->assertJsonPath('lines.1.status', 'unchanged')
            ->assertJsonPath('totals', ['new' => 0, 'updated' => 0, 'unchanged' => 2, 'errors' => 0]);
    }

    public function test_a_file_with_a_byte_order_mark_is_accepted(): void
    {
        $this->costRecord('K1');
        $file = UploadedFile::fake()->createWithContent('allocations.csv', "\xEF\xBB\xBF".self::HEADER."\nK1;2026;".self::OFFREREN.";100,00\n");

        $this->post('/canvases/'.$this->canvas->id.'/import/allocations', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('totals.new', 1);
        $this->assertSame(1, AllocationSet::count());
    }

    public function test_a_template_canvas_is_refused_with_a_rule_not_a_server_error(): void
    {
        $template = Canvas::factory()->template()->create(['is_blank_template' => true]);

        $this->upload(self::HEADER, ['K1;2026;'.self::OFFREREN.';100,00'], '/canvases/'.$template->id.'/import/allocations')->assertStatus(422);
        $this->upload(self::HEADER, [], '/canvases/'.$template->id.'/import/allocations')->assertOk();
    }

    public function test_a_row_with_more_cells_than_columns_is_a_line_error_and_a_year_must_be_digits(): void
    {
        $this->costRecord('K1');

        $response = $this->preview(['K1;2026;'.self::OFFREREN.';100,00;x;y', 'K1;20 26;'.self::OFFREREN.';100,00'])->assertOk();

        $this->assertStringContainsString('6 kolommen gevonden, verwacht 4', $this->messages($response)[0]);
        $this->assertSame('error', $response->json('lines.1.status'));
    }

    public function test_a_reader_is_refused_on_both_routes(): void
    {
        $this->costRecord('K1');
        $this->actingAs(User::factory()->create());

        $this->preview(['K1;2026;'.self::OFFREREN.';100,00'])->assertForbidden();
        $this->commit(['K1;2026;'.self::OFFREREN.';100,00'])->assertForbidden();
        $this->assertSame(0, AllocationSet::count());
    }
}
