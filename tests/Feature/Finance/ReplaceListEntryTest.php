<?php

namespace Tests\Feature\Finance;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReplaceListEntryTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->administrator = User::factory()->administrator()->create();
    }

    /** @return array<string, array{string, class-string<Application|Owner>, string}> */
    public static function lists(): array
    {
        return [
            'owners' => ['owners', Owner::class, 'owner_id'],
            'applications' => ['applications', Application::class, 'application_id'],
        ];
    }

    private function admin(): static
    {
        return $this->actingAs($this->administrator);
    }

    private function base(string $path): string
    {
        return "/admin/organisations/{$this->organisation->id}/{$path}";
    }

    /** @param class-string<Application|Owner> $model */
    private function entry(string $model, string $name): Application|Owner
    {
        return $model::factory()->create(['organisation_id' => $this->organisation->id, 'name' => $name]);
    }

    private function costRecord(string $column, int $id, bool $archived = false): CostRecord
    {
        return CostRecord::factory()->create([
            'organisation_id' => $this->organisation->id,
            $column => $id,
            'archived_at' => $archived ? now() : null,
        ]);
    }

    private function changedCostRecords(): int
    {
        return AuditEntry::query()->where('subject_type', 'cost_record')->where('action', 'changed')->count();
    }

    #[DataProvider('lists')]
    public function test_replacement_moves_every_cost_record_and_deletes_the_entry(string $path, string $model, string $column): void
    {
        $entry = $this->entry($model, 'Oud');
        $replacement = $this->entry($model, 'Nieuw');
        $first = $this->costRecord($column, $entry->id);
        $archived = $this->costRecord($column, $entry->id, true);
        $untouched = $this->costRecord($column, $replacement->id);

        $this->admin()->delete($this->base($path)."/{$entry->id}", ['replace_with' => (string) $replacement->id])->assertSessionHasNoErrors();

        $this->assertSame($replacement->id, $first->fresh()->{$column});
        $this->assertSame($replacement->id, $archived->fresh()->{$column});
        $this->assertSame($replacement->id, $untouched->fresh()->{$column});
        $this->assertNull($model::find($entry->id));
        $this->assertSame(2, $this->changedCostRecords());
    }

    public function test_an_application_may_be_replaced_by_no_application(): void
    {
        $entry = $this->entry(Application::class, 'Oud');
        $record = $this->costRecord('application_id', $entry->id);

        $this->admin()->delete($this->base('applications')."/{$entry->id}", ['replace_with' => 'none'])->assertSessionHasNoErrors();

        $this->assertNull($record->fresh()->application_id);
        $this->assertNull(Application::find($entry->id));
        $this->assertSame(1, $this->changedCostRecords());
    }

    public function test_an_owner_may_not_be_replaced_by_nothing(): void
    {
        $entry = $this->entry(Owner::class, 'Oud');
        $record = $this->costRecord('owner_id', $entry->id);

        $this->admin()->delete($this->base('owners')."/{$entry->id}", ['replace_with' => 'none'])->assertSessionHasErrors('rule');

        $this->assertSame($entry->id, $record->fresh()->owner_id);
        $this->assertNotNull(Owner::find($entry->id));
    }

    #[DataProvider('lists')]
    public function test_a_replacement_from_another_organisation_or_the_entry_itself_is_refused(string $path, string $model, string $column): void
    {
        $entry = $this->entry($model, 'Oud');
        $stranger = $model::factory()->create();
        $record = $this->costRecord($column, $entry->id);

        $this->admin()->delete($this->base($path)."/{$entry->id}", ['replace_with' => (string) $stranger->id])->assertSessionHasErrors('rule');
        $this->admin()->delete($this->base($path)."/{$entry->id}", ['replace_with' => (string) $entry->id])->assertSessionHasErrors('rule');

        $this->assertSame($entry->id, $record->fresh()->{$column});
        $this->assertNotNull($model::find($entry->id));
        $this->assertSame(0, $this->changedCostRecords());
    }

    public function test_the_replacement_runs_while_the_year_is_finalised(): void
    {
        $entry = $this->entry(Owner::class, 'Oud');
        $replacement = $this->entry(Owner::class, 'Nieuw');
        $record = $this->costRecord('owner_id', $entry->id);
        $year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id]);
        YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $year->id]);
        FinalisedYear::factory()->create([
            'canvas_id' => Canvas::factory()->create(['organisation_id' => $this->organisation->id])->id,
            'fiscal_year_id' => $year->id,
        ]);

        $this->admin()->delete($this->base('owners')."/{$entry->id}", ['replace_with' => (string) $replacement->id])->assertSessionHasNoErrors();

        $this->assertSame($replacement->id, $record->fresh()->owner_id);
    }

    #[DataProvider('lists')]
    public function test_an_unused_entry_is_deleted_with_an_undo_token_that_puts_the_row_back(string $path, string $model, string $column): void
    {
        $entry = $this->entry($model, 'Oud');

        $response = $this->admin()->delete($this->base($path)."/{$entry->id}");
        $response->assertSessionHasNoErrors();
        $token = session('undo');

        $this->assertIsString($token);
        $this->assertNull($model::find($entry->id));

        $this->admin()->post('/undo/'.$token)->assertSessionHasNoErrors();

        $restored = $model::findOrFail($entry->id);
        $this->assertSame('Oud', $restored->name);
        $this->assertSame($this->organisation->id, $restored->organisation_id);
    }

    #[DataProvider('lists')]
    public function test_an_undo_is_refused_when_the_name_is_taken_again(string $path, string $model, string $column): void
    {
        $entry = $this->entry($model, 'Oud');

        $this->admin()->delete($this->base($path)."/{$entry->id}");
        $token = (string) session('undo');
        $this->entry($model, ' oud ');

        $this->admin()->post('/undo/'.$token)->assertSessionHasErrors('rule');
        $this->assertNull($model::find($entry->id));
    }

    #[DataProvider('lists')]
    public function test_an_undo_is_refused_when_the_organisation_was_archived(string $path, string $model, string $column): void
    {
        $entry = $this->entry($model, 'Oud');

        $this->admin()->delete($this->base($path)."/{$entry->id}");
        $token = (string) session('undo');
        $this->organisation->update(['archived_at' => now()]);

        $this->admin()->post('/undo/'.$token)->assertSessionHasErrors('rule');
        $this->assertNull($model::find($entry->id));
    }

    #[DataProvider('lists')]
    public function test_a_used_entry_without_a_replacement_is_still_refused(string $path, string $model, string $column): void
    {
        $entry = $this->entry($model, 'Oud');
        $this->costRecord($column, $entry->id);

        $this->admin()->delete($this->base($path)."/{$entry->id}")->assertSessionHasErrors('rule');
        $this->assertNotNull($model::find($entry->id));
    }

    /** @return array<string, array{mixed}> */
    public static function malformedReplacements(): array
    {
        return [
            'array' => [[1]],
            'text' => ['abc'],
            'negative' => ['-3'],
            'decimal' => ['1.5'],
            'zero' => ['0'],
        ];
    }

    #[DataProvider('malformedReplacements')]
    public function test_a_malformed_replacement_is_a_validation_error_not_a_server_error(mixed $replacement): void
    {
        $entry = Owner::factory()->for($this->organisation)->create();

        $this->admin()->delete($this->base('owners')."/{$entry->id}", ['replace_with' => $replacement])->assertSessionHasErrors('replace_with');

        $this->assertModelExists($entry);
    }
}
