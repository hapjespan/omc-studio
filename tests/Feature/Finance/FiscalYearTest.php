<?php

namespace Tests\Feature\Finance;

use App\Models\AuditEntry;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\User;
use App\Models\YearlyFigure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdministrator;
use Tests\TestCase;

class FiscalYearTest extends TestCase
{
    use ActsAsAdministrator;
    use RefreshDatabase;

    private const REFUSAL = 'Boekjaar: kies een jaar van 2000 tot en met 2100 dat nog niet bestaat';

    public function test_a_year_is_added_and_audited(): void
    {
        $organisation = Organisation::factory()->create();

        $this->admin()->post("/organisations/{$organisation->id}/fiscal-years", ['year' => '2026'])->assertRedirect();

        $this->assertSame([2026], $organisation->fiscalYears()->pluck('year')->all());
        $this->assertSame(1, AuditEntry::where('action', 'created')->where('subject_type', 'fiscal_year')->where('organisation_id', $organisation->id)->count());
    }

    public function test_out_of_range_duplicate_and_non_numeric_years_are_refused(): void
    {
        $organisation = Organisation::factory()->create();
        FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);

        foreach (['1999', '2101', '2026', 'abc', '', '20.5'] as $year) {
            $this->admin()->post("/organisations/{$organisation->id}/fiscal-years", ['year' => $year])->assertSessionHasErrors(['year' => self::REFUSAL]);
        }

        $this->assertSame(1, $organisation->fiscalYears()->count());
    }

    public function test_the_same_year_is_allowed_in_another_organisation(): void
    {
        $other = FiscalYear::factory()->create(['year' => 2026]);
        $organisation = Organisation::factory()->create();

        $this->admin()->post("/organisations/{$organisation->id}/fiscal-years", ['year' => 2026])->assertSessionHasNoErrors();

        $this->assertSame(2, FiscalYear::where('year', 2026)->count());
        $this->assertNotSame($other->organisation_id, $organisation->id);
    }

    public function test_a_year_with_yearly_figures_is_not_deleted_and_an_empty_one_is(): void
    {
        $figure = YearlyFigure::factory()->create();
        $empty = FiscalYear::factory()->create(['organisation_id' => $figure->fiscalYear->organisation_id, 'year' => 2031]);

        $this->admin()->delete("/fiscal-years/{$figure->fiscal_year_id}")->assertSessionHasErrors(['rule' => 'Dit boekjaar heeft nog jaarwaarden']);
        $this->assertNotNull($figure->fiscalYear()->first());

        $this->admin()->delete("/fiscal-years/{$empty->id}")->assertSessionHasNoErrors();
        $this->assertNull(FiscalYear::find($empty->id));
    }

    public function test_an_archived_organisation_is_refused(): void
    {
        $organisation = Organisation::factory()->create(['archived_at' => now()]);

        $this->admin()->post("/organisations/{$organisation->id}/fiscal-years", ['year' => 2026])->assertSessionHasErrors('rule');
        $this->assertSame(0, FiscalYear::count());
    }

    public function test_a_reader_and_a_guest_are_refused(): void
    {
        $organisation = Organisation::factory()->create();

        $this->actingAs(User::factory()->create())->post("/organisations/{$organisation->id}/fiscal-years", ['year' => 2026])->assertForbidden();
        auth()->logout();
        $this->post("/organisations/{$organisation->id}/fiscal-years", ['year' => 2026])->assertRedirect('/sign-in');
    }
}
