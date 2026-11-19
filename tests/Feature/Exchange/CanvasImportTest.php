<?php

namespace Tests\Feature\Exchange;

use App\Models\AllocationSet;
use App\Models\Application;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\Statement;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use RuntimeException;

class CanvasImportTest extends ExchangeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->richCanvas();
        $this->actingAs($this->admin());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  ?array<string, mixed>  $data
     * @return TestResponse<JsonResponse>
     */
    private function send(string $path, array $payload, ?array $data = null): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('canvas.json', (string) json_encode($data ?? $this->exported()));

        return $this->post($path, ['file' => $file, ...$payload], ['Accept' => 'application/json']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  ?array<string, mixed>  $data
     * @return TestResponse<JsonResponse>
     */
    private function preview(array $payload, ?array $data = null): TestResponse
    {
        return $this->send('/imports/canvas/preview', $payload, $data);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  ?array<string, mixed>  $data
     * @return TestResponse<JsonResponse>
     */
    private function commit(array $payload, ?array $data = null): TestResponse
    {
        return $this->send('/imports/canvas', $payload, $data);
    }

    /** @return list<int> */
    private function counts(): array
    {
        return [Organisation::count(), Canvas::count(), Element::count(), CostRecord::count(), YearlyFigure::count(), AllocationSet::count(), AuditEntry::count()];
    }

    /** @return array<string, array<int, mixed>> */
    private function structure(Canvas $canvas): array
    {
        $names = Element::where('canvas_id', $canvas->getKey())->pluck('name', 'id')->all();
        $rows = [];

        foreach (Element::where('canvas_id', $canvas->getKey())->get() as $element) {
            $rows[$element->name] = [
                $element->kind->value,
                $element->area_id,
                $element->parent_id === null ? null : $names[$element->parent_id],
                $element->description,
                $element->modality_id,
                $element->rationale,
                $element->position,
                $element->is_unknown_allocation,
                $element->archived_at?->toDateTimeString(),
            ];
        }

        ksort($rows);

        return $rows;
    }

    /** @return array<string, array<int, mixed>> */
    private function records(int $organisationId): array
    {
        $years = FiscalYear::where('organisation_id', $organisationId)->pluck('year', 'id')->all();
        $rows = [];

        foreach (CostRecord::with(['owner', 'application', 'yearlyFigures'])->where('organisation_id', $organisationId)->get() as $record) {
            $rows[(string) $record->code] = [
                $record->name,
                $record->cost_kind->value,
                $record->supplier,
                $record->cost_centre,
                $record->owner->name,
                $record->application?->name,
                $record->description,
                $record->archived_at?->toDateTimeString(),
                $record->yearlyFigures->mapWithKeys(fn (YearlyFigure $figure) => [$years[$figure->fiscal_year_id] => [
                    $figure->budget_cents, $figure->forecast_cents, $figure->run_bp, $figure->change_bp, $figure->fte_hundredths,
                    $figure->reference_date?->format('Y-m-d'), $figure->remark,
                ]])->sortKeys()->all(),
            ];
        }

        ksort($rows);

        return $rows;
    }

    /** @return array<string, list<array<int, mixed>>> */
    private function allocations(Canvas $canvas): array
    {
        $years = FiscalYear::pluck('year', 'id')->all();
        $codes = CostRecord::pluck('code', 'id')->all();
        $names = Element::where('canvas_id', $canvas->getKey())->pluck('name', 'id')->all();
        $rows = [];

        foreach (AllocationSet::with('lines')->where('canvas_id', $canvas->getKey())->get() as $set) {
            $rows[$codes[$set->cost_record_id].'|'.$years[$set->fiscal_year_id]] = $set->lines
                ->map(fn ($line) => [$names[$line->element_id], $line->basis_points, $line->position])->all();
        }

        ksort($rows);

        return $rows;
    }

    public function test_a_clean_dry_run_reports_no_errors_and_the_totals_of_the_file(): void
    {
        $this->preview(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'])
            ->assertOk()
            ->assertExactJson([
                'errors' => [],
                'count' => 0,
                'totals' => ['cost_records' => 2, 'reused' => 0, 'elements' => 7, 'statements' => 2, 'allocation_sets' => 4, 'finalised_years' => 1],
            ]);
    }

    public function test_a_dry_run_into_the_source_organisation_counts_the_reusable_cost_records(): void
    {
        $this->preview(['organisation_id' => $this->canvas->organisation_id, 'canvas_name' => 'Kopie'])
            ->assertOk()
            ->assertJsonPath('errors', [])
            ->assertJsonPath('totals.reused', 2);
    }

    public function test_a_dry_run_reports_every_fault_with_its_location_in_the_file(): void
    {
        $data = $this->exported();
        $data['canvas']['elements'][0]['name'] = str_repeat('a', 151);
        $data['cost_records'][0]['yearly_figures'][0]['budget_cents'] = -1;

        $errors = $this->preview(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'], $data)->assertOk()->json('errors');

        $this->assertSame(['cost_records[0].yearly_figures[0].budget_cents', 'canvas.elements[0].name'], array_column($errors, 'location'));
    }

    public function test_a_file_that_is_not_an_export_of_this_application_is_refused(): void
    {
        $this->preview(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'], ['format' => 'something-else'])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Bestand: kies een JSON-export van deze applicatie van maximaal 50 MB');
    }

    public function test_a_commit_into_a_new_organisation_rebuilds_the_canvas_with_new_identifiers(): void
    {
        $source = $this->canvas->organisation;
        $elementIds = Element::where('canvas_id', $this->canvas->getKey())->pluck('id')->all();
        $recordIds = CostRecord::pluck('id')->all();

        $response = $this->commit(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'])->assertOk();

        $copy = Canvas::findOrFail($response->json('canvas_id'));
        $target = Organisation::where('name', 'Doelorganisatie')->sole();

        $this->assertNotSame($this->canvas->getKey(), $copy->getKey());
        $this->assertSame($target->getKey(), $copy->organisation_id);
        $this->assertSame($target->getKey(), $response->json('organisation_id'));
        $this->assertSame(['cost_records' => 2, 'reused' => 0, 'elements' => 7, 'statements' => 2, 'allocation_sets' => 4, 'finalised_years' => 1], $response->json('totals'));

        $this->assertSame([], array_intersect($elementIds, Element::where('canvas_id', $copy->getKey())->pluck('id')->all()));
        $this->assertSame([], array_intersect($recordIds, CostRecord::where('organisation_id', $target->getKey())->pluck('id')->all()));

        $this->assertSame($this->structure($this->canvas), $this->structure($copy));
        $this->assertSame(1, Element::where('canvas_id', $copy->getKey())->where('is_unknown_allocation', true)->count());
        $this->assertSame(8, Element::where('canvas_id', $copy->getKey())->count());

        $statements = fn (Canvas $canvas) => Statement::where('canvas_id', $canvas->getKey())->orderBy('kind')->orderBy('position')->get()
            ->map(fn (Statement $statement) => [$statement->kind->value, $statement->title, $statement->body, $statement->position])->all();
        $this->assertSame($statements($this->canvas), $statements($copy));

        $names = fn (string $class, int $organisationId) => $class::where('organisation_id', $organisationId)->orderBy('name')->pluck('name')->all();
        $this->assertSame($names(Owner::class, $source->getKey()), $names(Owner::class, $target->getKey()));
        $this->assertSame($names(Application::class, $source->getKey()), $names(Application::class, $target->getKey()));
        $this->assertSame('Leverancier X', Application::where('organisation_id', $target->getKey())->where('name', 'ERP')->value('supplier'));
        $this->assertSame([2026, 2027], FiscalYear::where('organisation_id', $target->getKey())->orderBy('year')->pluck('year')->all());

        $this->assertSame($this->records($source->getKey()), $this->records($target->getKey()));
        $this->assertSame($this->allocations($this->canvas), $this->allocations($copy));

        $finalised = FinalisedYear::where('canvas_id', $copy->getKey())->sole();
        $this->assertSame(2027, FiscalYear::findOrFail($finalised->fiscal_year_id)->year);
        $this->assertNull($finalised->finalised_by);
    }

    public function test_a_second_import_into_the_same_organisation_makes_a_second_canvas_and_reuses_the_cost_records(): void
    {
        $organisation = $this->canvas->organisation;

        $this->commit(['organisation_id' => $organisation->getKey(), 'canvas_name' => 'Kopie een'])->assertOk()->assertJsonPath('totals.reused', 2);

        $records = CostRecord::count();
        $figures = YearlyFigure::count();

        $this->commit(['organisation_id' => $organisation->getKey(), 'canvas_name' => 'Kopie twee'])->assertOk();

        $this->assertSame($records, CostRecord::count());
        $this->assertSame($figures, YearlyFigure::count());
        $this->assertSame(3, Canvas::where('organisation_id', $organisation->getKey())->count());
        $this->assertSame(['Basis canvas', 'Kopie een', 'Kopie twee'], Canvas::orderBy('name')->pluck('name')->all());
    }

    public function test_every_imported_row_gets_an_audit_entry_with_the_action_imported(): void
    {
        $canvasId = $this->commit(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'])->assertOk()->json('canvas_id');

        foreach (['organisation', 'canvas', 'statement', 'element', 'cost_record', 'yearly_figure', 'allocation_set', 'finalised_year'] as $subject) {
            $this->assertTrue(AuditEntry::where('action', 'imported')->where('subject_type', $subject)->exists(), $subject);
        }

        $this->assertSame([$canvasId], AuditEntry::where('subject_type', 'element')->where('action', 'imported')->pluck('canvas_id')->unique()->values()->all());
        $this->assertSame([$canvasId], AuditEntry::where('subject_type', 'allocation_set')->where('action', 'imported')->pluck('canvas_id')->unique()->values()->all());
    }

    public function test_one_security_log_entry_is_written_per_commit(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $response = $this->commit(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'])->assertOk();

        $entries = array_values(array_filter($logged, fn (MessageLogged $event) => $event->message === 'canvas_imported'));

        $this->assertCount(1, $entries);
        $this->assertSame(['organisation_id' => $response->json('organisation_id'), 'canvas_id' => $response->json('canvas_id'), 'user_id' => auth()->id()], $entries[0]->context);
    }

    public function test_a_commit_of_a_file_with_errors_writes_nothing(): void
    {
        $before = $this->counts();
        $data = $this->exported();
        $data['canvas']['elements'][0]['name'] = str_repeat('a', 151);

        $this->commit(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'], $data)
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Importeren is niet gelukt; het bestand bevat fouten. Er is niets gewijzigd');

        $this->assertSame($before, $this->counts());
    }

    public function test_a_failure_during_the_write_leaves_nothing_written(): void
    {
        $this->app->instance(AuditTrail::class, new class(app(AuditContext::class)) extends AuditTrail
        {
            /** @param list<array{string, string, array<string, array{mixed, mixed}>}> $entries */
            public function recordMany(string $subjectType, string $action, ?int $organisationId, array $entries, ?int $canvasId = null): void
            {
                if ($subjectType === 'allocation_set') {
                    throw new RuntimeException('broken');
                }

                parent::recordMany($subjectType, $action, $organisationId, $entries, $canvasId);
            }
        });

        $before = $this->counts();

        $this->commit(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'])->assertStatus(500);

        $this->assertSame($before, $this->counts());
    }

    public function test_an_archived_target_organisation_is_refused(): void
    {
        $organisation = $this->canvas->organisation;
        $organisation->update(['archived_at' => now()]);
        $before = $this->counts();

        $this->commit(['organisation_id' => $organisation->getKey(), 'canvas_name' => 'Kopie'])
            ->assertStatus(422)
            ->assertJsonPath('errors.rule.0', 'Deze organisatie is gearchiveerd');

        $this->assertSame($before, $this->counts());
    }

    public function test_a_reader_may_neither_preview_nor_import(): void
    {
        $this->actingAs(User::factory()->create());
        $before = $this->counts();

        $this->preview(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'])->assertForbidden();
        $this->commit(['organisation_name' => 'Doelorganisatie', 'canvas_name' => 'Kopie'])->assertForbidden();

        $this->assertSame($before, $this->counts());
    }

    public function test_a_commit_without_a_target_and_without_a_name_is_refused(): void
    {
        $this->commit(['canvas_name' => 'Kopie'])->assertStatus(422)->assertJsonValidationErrors('organisation_name');
    }
}
