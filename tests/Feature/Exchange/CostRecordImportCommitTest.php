<?php

namespace Tests\Feature\Exchange;

use App\Models\Application;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Support\SerialisedWrite;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CostRecordImportCommitTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

    private Organisation $organisation;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
        $this->actingAs(User::factory()->administrator()->create());
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<JsonResponse>
     */
    private function commit(array $lines): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', implode("\n", [self::HEADER, ...$lines])."\n");

        return $this->post('/organisations/'.$this->organisation->id.'/cost-records/import', ['file' => $file], ['Accept' => 'application/json']);
    }

    private function hook(Closure $hook): void
    {
        $this->app->instance(SerialisedWrite::class, new class($hook) extends SerialisedWrite
        {
            public function __construct(private readonly Closure $hook) {}

            /**
             * @template TReturn
             *
             * @param  array<int, Model>  $aggregates
             * @param  Closure(): TReturn  $write
             * @return TReturn
             */
            public function run(array $aggregates, Closure $write): mixed
            {
                ($this->hook)();

                return parent::run($aggregates, $write);
            }
        });
    }

    public function test_a_clean_file_creates_records_figures_owners_and_applications(): void
    {
        FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2027]);

        $response = $this->commit([
            'K1;Licenties;Leverancierscontract;Acme;Ada;KP1;Portal;2026;1.000,00;900,00;60;40;1,5',
            'K2;Hosting;overig;;Bo;;;;;;;;',
            'K1;Licenties;Leverancierscontract;Acme;Ada;KP1;Portal;2027;1.100,00;;;;',
        ])->assertOk();

        $response->assertJsonPath('totals', ['new' => 3, 'updated' => 0, 'unchanged' => 0, 'errors' => 0]);

        $record = CostRecord::where('code_normalised', 'k1')->sole();
        $this->assertSame(['Licenties', 'supplier_contract', 'Acme', 'KP1'], [$record->name, $record->cost_kind->value, $record->supplier, $record->cost_centre]);
        $this->assertSame('Ada', $record->owner->name);
        $this->assertSame('Portal', $record->application?->name);

        $figure = $record->yearlyFigures()->where('fiscal_year_id', $this->year->id)->sole();
        $this->assertSame([100000, 90000, 6000, 4000, 150], [$figure->budget_cents, $figure->forecast_cents, $figure->run_bp, $figure->change_bp, $figure->fte_hundredths]);
        $this->assertSame([100000, 110000], $record->yearlyFigures()->orderBy('fiscal_year_id')->pluck('budget_cents')->all());

        $this->assertSame(2, CostRecord::count());
        $this->assertSame(2, YearlyFigure::count());
        $this->assertSame(['Ada', 'Bo'], Owner::orderBy('name')->pluck('name')->all());
        $this->assertSame(['Portal'], Application::pluck('name')->all());
    }

    public function test_every_imported_record_gets_an_audit_entry_with_the_action_imported(): void
    {
        $this->commit(['K1;Licenties;Project;;Ada;;Portal;2026;10,00;;;;'])->assertOk();

        $subjects = AuditEntry::where('action', 'imported')->orderBy('id')->pluck('subject_type')->all();

        $this->assertSame(['owner', 'application', 'cost_record', 'yearly_figure'], $subjects);
        $this->assertNull(AuditEntry::where('subject_type', 'yearly_figure')->value('organisation_id'));
        $this->assertSame(0, AuditEntry::whereIn('action', ['created', 'changed'])->where('subject_type', 'cost_record')->count());
    }

    public function test_a_second_import_of_the_same_file_changes_nothing(): void
    {
        $lines = ['K1;Licenties;Project;Acme;Ada;;Portal;2026;10,00;;;;', 'K2;Hosting;overig;;Bo;;;;;;;;'];

        $this->commit($lines)->assertOk();
        $entries = AuditEntry::count();

        $this->commit($lines)->assertOk()->assertJsonPath('totals', ['new' => 0, 'updated' => 0, 'unchanged' => 2, 'errors' => 0]);

        $this->assertSame($entries, AuditEntry::count());
        $this->assertSame(2, CostRecord::count());
        $this->assertSame(1, YearlyFigure::count());
        $this->assertSame(2, Owner::count());
    }

    public function test_an_emptied_cell_clears_the_field_of_an_existing_record(): void
    {
        $record = CostRecord::factory()->create([
            'organisation_id' => $this->organisation->id,
            'code' => 'K1',
            'name' => 'Licenties',
            'cost_kind' => 'project',
            'supplier' => 'Acme',
            'cost_centre' => 'KP1',
            'application_id' => Application::factory()->create(['organisation_id' => $this->organisation->id])->id,
        ]);
        $record->owner->update(['name' => 'Ada']);
        YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 500, 'forecast_cents' => 400, 'fte_hundredths' => 200]);

        $this->commit(['K1;Licenties;Project;;ada;;;2026;10,00;;;;'])->assertOk()
            ->assertJsonPath('totals.updated', 1);

        $record->refresh();
        $this->assertSame([null, null, null], [$record->supplier, $record->cost_centre, $record->application_id]);
        $this->assertSame('K1', $record->code);

        $figure = $record->yearlyFigures()->sole();
        $this->assertSame([1000, null, null], [$figure->budget_cents, $figure->forecast_cents, $figure->fte_hundredths]);
    }

    public function test_a_file_with_an_error_is_refused_and_writes_nothing(): void
    {
        $this->commit(['K1;Licenties;Project;;Ada;;;2026;10,00;;;;', ';Hosting;Project;;Bo;;;;;;;;'])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Importeren is niet gelukt; het bestand bevat fouten. Er is niets gewijzigd');

        $this->assertSame(0, CostRecord::count());
        $this->assertSame(0, Owner::count());
        $this->assertSame(0, AuditEntry::where('action', 'imported')->count());
    }

    public function test_over_long_values_are_refused_and_write_nothing(): void
    {
        $this->commit([str_repeat('a', 51).';Licenties;Project;;Ada;;;;;;;;'])->assertStatus(422);

        $this->assertSame(0, CostRecord::count());
    }

    public function test_one_security_log_entry_is_written_per_commit_without_personal_data(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $this->commit(['K1;Licenties;Project;;Ada;;;2026;10,00;;;;'])->assertOk();
        $this->commit([';Hosting;Project;;Bo;;;;;;;;'])->assertStatus(422);

        $entries = array_values(array_filter($logged, fn (MessageLogged $event) => $event->message === 'cost_records_imported'));
        $this->assertCount(1, $entries);
        $this->assertSame(['organisation_id' => $this->organisation->id, 'new' => 1, 'updated' => 0, 'unchanged' => 0, 'errors' => 0, 'user_id' => auth()->id()], $entries[0]->context);
        $this->assertStringNotContainsString('Ada', (string) json_encode($entries[0]->context));
    }

    public function test_a_year_finalised_between_the_check_and_the_write_rolls_everything_back(): void
    {
        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Strategie']);

        $this->hook(fn () => FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => $this->year->id]));

        $this->commit(['K1;Licenties;Project;;Ada;;Portal;2026;10,00;;;;'])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Importeren is niet gelukt; de gegevens zijn ondertussen gewijzigd. Er is niets gewijzigd. Controleer het bestand opnieuw');

        $this->assertSame(0, CostRecord::count());
        $this->assertSame(0, YearlyFigure::count());
        $this->assertSame(0, Owner::count());
        $this->assertSame(0, Application::count());
        $this->assertSame(0, AuditEntry::where('action', 'imported')->count());
    }

    public function test_a_code_appearing_between_the_check_and_the_write_rolls_everything_back(): void
    {
        $this->hook(fn () => CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'code' => 'K1', 'name' => 'Elders']));

        $this->commit(['K1;Licenties;Project;;Ada;;;2026;10,00;;;;'])->assertStatus(422);

        $this->assertSame('Elders', CostRecord::sole()->name);
        $this->assertSame(0, YearlyFigure::count());
    }

    public function test_a_record_archived_between_the_check_and_the_write_is_not_overwritten(): void
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'code' => 'K1', 'name' => 'Licenties']);
        $this->hook(fn () => $record->update(['archived_at' => now()]));

        $this->commit(['K1;Hernoemd;Project;;Ada;;;2026;10,00;;;;'])->assertStatus(422);

        $this->assertSame('Licenties', $record->fresh()->name);
    }

    public function test_a_non_administrator_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->commit(['K1;Licenties;Project;;Ada;;;2026;10,00;;;;'])->assertForbidden();

        $this->assertSame(0, CostRecord::count());
    }

    public function test_an_archived_organisation_refuses_the_import(): void
    {
        $this->organisation->update(['archived_at' => now()]);

        $this->commit(['K1;Licenties;Project;;Ada;;;2026;10,00;;;;'])
            ->assertStatus(422)
            ->assertJsonPath('errors.rule.0', __('rule.organisation_archived'));

        $this->assertSame(0, CostRecord::count());
        $this->assertSame(0, Owner::count());
    }

    public function test_a_line_that_changes_an_archived_cost_record_or_its_figures_is_an_error_and_nothing_is_written(): void
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'code' => 'K1', 'name' => 'Licenties', 'cost_kind' => 'project', 'supplier' => null, 'cost_centre' => null, 'application_id' => null, 'archived_at' => now()]);
        $record->update(['owner_id' => Owner::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ada'])->id]);
        YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 1000, 'forecast_cents' => null, 'run_bp' => null, 'change_bp' => null, 'fte_hundredths' => null]);

        $this->commit(['K1;Licenties;Project;;Ada;;;2026;10,00;;;;'])->assertOk()->assertJsonPath('totals.unchanged', 1);

        $this->commit(['K1;Hernoemd;Project;;Ada;;;2026;10,00;;;;'])->assertStatus(422);
        $this->commit(['K1;Licenties;Project;;Ada;;;2026;99,00;;;;'])->assertStatus(422);

        $this->assertSame('Licenties', $record->fresh()->name);
        $this->assertSame(1000, $record->yearlyFigures()->sole()->budget_cents);
    }
}
