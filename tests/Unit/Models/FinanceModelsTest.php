<?php

namespace Tests\Unit\Models;

use App\Enums\CostKind;
use App\Models\Application;
use App\Models\AuditEntry;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\YearlyFigure;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventSilentlyDiscardingAttributes();
    }

    protected function tearDown(): void
    {
        Model::preventSilentlyDiscardingAttributes(false);

        parent::tearDown();
    }

    public function test_names_and_codes_are_normalised_on_save(): void
    {
        $owner = Owner::factory()->create(['name' => '  Afdeling ICT ']);
        $application = Application::factory()->create(['name' => ' Planningssysteem ', 'supplier' => 'Leverancier X']);
        $record = CostRecord::factory()->create(['code' => '  AB-01 ']);

        $this->assertSame('afdeling ict', $owner->fresh()->name_normalised);
        $this->assertSame('planningssysteem', $application->fresh()->name_normalised);
        $this->assertSame('ab-01', $record->fresh()->code_normalised);
        $this->assertTrue(Str::isUuid($record->id));
        $this->assertSame(CostKind::Other, $record->fresh()->cost_kind);
    }

    public function test_an_empty_code_is_stored_as_null_and_may_repeat(): void
    {
        $organisation = Organisation::factory()->create();

        $first = CostRecord::factory()->for($organisation)->create(['code' => '   ']);
        $second = CostRecord::factory()->for($organisation)->create(['code' => null]);

        $this->assertNull($first->fresh()->code_normalised);
        $this->assertNull($second->fresh()->code_normalised);
    }

    public function test_a_code_is_unique_per_organisation_ignoring_case(): void
    {
        $organisation = Organisation::factory()->create();
        CostRecord::factory()->for($organisation)->create(['code' => 'ab-01']);

        CostRecord::factory()->create(['code' => 'AB-01']);

        $this->expectException(UniqueConstraintViolationException::class);

        CostRecord::factory()->for($organisation)->create(['code' => ' AB-01']);
    }

    public function test_every_change_writes_one_audit_entry_with_old_and_new_values(): void
    {
        $record = CostRecord::factory()->create(['name' => 'Licenties']);
        $figure = YearlyFigure::factory()->for($record)->create(['budget_cents' => 1000]);
        $year = FiscalYear::factory()->create(['year' => 2030]);
        $owner = Owner::factory()->create();
        $application = Application::factory()->create();
        AuditEntry::query()->delete();

        $record->update(['name' => 'Licenties 2']);
        $figure->update(['budget_cents' => 2500]);
        $year->update(['year' => 2031]);
        $owner->update(['name' => 'Anders']);
        $application->update(['supplier' => 'Nieuw']);

        $this->assertSame(5, AuditEntry::count());
        $entry = AuditEntry::where('subject_type', 'cost_record')->sole();
        $this->assertSame(['Licenties', 'Licenties 2'], $entry->changes['name']);
        $this->assertSame([1000, 2500], AuditEntry::where('subject_type', 'yearly_figure')->sole()->changes['budget_cents']);
    }

    public function test_keys_cannot_be_mass_assigned(): void
    {
        $this->expectException(MassAssignmentException::class);

        $this->freshModel()->fill(['id' => 'x']);
    }

    public function test_relations_load_eagerly_and_lazy_loading_is_refused(): void
    {
        $record = CostRecord::factory()->create();
        YearlyFigure::factory()->for($record)->create();
        CostRecord::factory()->create();

        $loaded = CostRecord::with(['organisation', 'owner', 'application', 'yearlyFigures.fiscalYear'])->find($record->id);
        $this->assertSame($record->organisation_id, $loaded->organisation->id);
        $this->assertCount(1, $loaded->yearlyFigures);

        $this->expectException(LazyLoadingViolationException::class);

        foreach (CostRecord::get() as $loadedRecord) {
            $loadedRecord->getRelationValue('owner');
        }
    }

    public function test_an_organisation_has_its_fiscal_years_owners_and_applications(): void
    {
        $organisation = Organisation::factory()->create();
        FiscalYear::factory()->for($organisation)->create(['year' => 2026]);
        Owner::factory()->for($organisation)->create();
        Application::factory()->for($organisation)->create();

        $loaded = Organisation::with(['fiscalYears', 'owners', 'applications'])->find($organisation->id);

        $this->assertCount(1, $loaded->fiscalYears);
        $this->assertCount(1, $loaded->owners);
        $this->assertCount(1, $loaded->applications);
    }

    private function freshModel(): CostRecord
    {
        return new CostRecord;
    }
}
