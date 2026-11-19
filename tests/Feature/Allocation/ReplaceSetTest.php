<?php

namespace Tests\Feature\Allocation;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReplaceSetTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Canvas $canvas;

    private CostRecord $record;

    private FiscalYear $year;

    private Element $domain;

    private Element $first;

    private Element $second;

    private ?User $administrator = null;

    protected function setUp(): void
    {
        parent::setUp();

        app(ReferenceDataStep::class)();

        $this->organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id]);
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
        $this->record = $this->costRecord();
        YearlyFigure::factory()->create(['cost_record_id' => $this->record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 23_000]);

        $this->domain = $this->element('Inkoop');
        $this->first = $this->element('Offreren', ['kind' => ElementKind::Activity, 'parent_id' => $this->domain->id]);
        $this->second = $this->element('Bestellen', ['kind' => ElementKind::Activity, 'parent_id' => $this->domain->id]);
    }

    private function costRecord(): CostRecord
    {
        return CostRecord::factory()->create([
            'organisation_id' => $this->organisation->id,
            'owner_id' => Owner::factory()->create(['organisation_id' => $this->organisation->id])->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function element(string $name, array $overrides = []): Element
    {
        return Element::factory()->create(array_merge([
            'canvas_id' => $this->canvas->id,
            'area_id' => Area::query()->where('key', 'primary_activities')->firstOrFail()->id,
            'kind' => ElementKind::Domain,
            'name' => $name,
        ], $overrides));
    }

    private function admin(): static
    {
        return $this->actingAs($this->administrator ??= User::factory()->administrator()->create());
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return TestResponse<Response>
     */
    private function replace(array $lines, ?CostRecord $record = null, int $year = 2026): TestResponse
    {
        $record ??= $this->record;

        return $this->admin()->put("/canvases/{$this->canvas->id}/years/{$year}/cost-records/{$record->id}/allocation", ['lines' => $lines]);
    }

    /** @return array<string, mixed> */
    private function line(Element $element, int|string $basisPoints): array
    {
        return ['element_id' => $element->id, 'basis_points' => $basisPoints];
    }

    /** @return list<array{string, int}> */
    private function stored(): array
    {
        return AllocationLine::query()->with('element')->orderBy('position')
            ->get()->map(fn (AllocationLine $line) => [(string) $line->element?->name, $line->basis_points])->all();
    }

    private function seedSet(): AllocationSet
    {
        $set = AllocationSet::factory()->create([
            'canvas_id' => $this->canvas->id,
            'cost_record_id' => $this->record->id,
            'fiscal_year_id' => $this->year->id,
        ]);

        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $this->domain->id, 'basis_points' => 10_000, 'position' => 0]);

        return $set;
    }

    public function test_a_set_is_stored_whole_in_line_order_with_one_audit_entry_naming_old_and_new(): void
    {
        $this->replace([$this->line($this->first, 3333), $this->line($this->second, 3333), $this->line($this->domain, 3334)])
            ->assertSessionHasNoErrors();

        $this->assertSame('Verdeling opgeslagen', session('status'));
        $this->assertSame(1, AllocationSet::count());
        $this->assertSame([['Offreren', 3333], ['Bestellen', 3333], ['Inkoop', 3334]], $this->stored());

        $set = AllocationSet::firstOrFail();
        $this->assertSame($this->canvas->id, $set->canvas_id);
        $this->assertSame($this->record->id, $set->cost_record_id);
        $this->assertSame($this->year->id, $set->fiscal_year_id);

        $entries = AuditEntry::query()->where('subject_type', 'allocation_set')->get();
        $this->assertCount(1, $entries);
        $this->assertSame('allocation_changed', $entries[0]->action);
        $this->assertSame('allocation_set', $entries[0]->subject_type);
        $this->assertSame([
            [],
            [
                ['target' => 'Offreren', 'percentage' => '33,33%'],
                ['target' => 'Bestellen', 'percentage' => '33,33%'],
                ['target' => 'Inkoop', 'percentage' => '33,34%'],
            ],
        ], $entries[0]->changes['lines']);
    }

    public function test_a_second_save_replaces_the_whole_set_and_the_entry_holds_the_old_set(): void
    {
        $this->replace([$this->line($this->first, 10_000)])->assertSessionHasNoErrors();
        $this->replace([$this->line($this->second, 5000), $this->line($this->domain, 5000)])->assertSessionHasNoErrors();

        $this->assertSame(1, AllocationSet::count());
        $this->assertSame([['Bestellen', 5000], ['Inkoop', 5000]], $this->stored());

        $entries = AuditEntry::query()->where('action', 'allocation_changed')->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame([['target' => 'Offreren', 'percentage' => '100,00%']], $entries[1]->changes['lines'][0]);
    }

    public function test_the_lines_must_add_up_to_exactly_one_hundred_percent(): void
    {
        $this->seedSet();

        $this->replace([$this->line($this->first, 3333), $this->line($this->second, 6666)])
            ->assertSessionHasErrors(['rule' => 'De verdeling moet precies 100,00% zijn; nu 99,99%']);
        $this->replace([$this->line($this->first, 3334), $this->line($this->second, 6667)])
            ->assertSessionHasErrors(['rule' => 'De verdeling moet precies 100,00% zijn; nu 100,01%']);
        $this->replace([])->assertSessionHasErrors(['rule' => 'De verdeling moet precies 100,00% zijn; nu 0,00%']);

        $this->assertSame([['Inkoop', 10_000]], $this->stored());
    }

    public function test_every_line_holds_whole_basis_points_from_one_to_ten_thousand(): void
    {
        foreach ([0, -5, 10_001, '33,5', '3333.5', ''] as $basisPoints) {
            $this->replace([$this->line($this->first, $basisPoints), $this->line($this->second, 10_000)])
                ->assertSessionHasErrors(['rule' => 'Regel Offreren: kies een percentage van 0,01% tot 100,00%']);
        }

        $this->assertSame(0, AllocationSet::count());
    }

    public function test_a_target_may_not_appear_twice(): void
    {
        $this->replace([$this->line($this->first, 5000), $this->line($this->first, 5000)])
            ->assertSessionHasErrors(['rule' => 'Doel Offreren staat twee keer in de verdeling']);

        $this->assertSame(0, AllocationSet::count());
    }

    public function test_only_active_targets_of_this_canvas_in_an_activity_area_carry_costs(): void
    {
        $actors = $this->element('Leveranciers', ['area_id' => Area::query()->where('key', 'actors')->firstOrFail()->id]);
        $archived = $this->element('Gestopt', ['archived_at' => now()]);
        $elsewhere = Element::factory()->create([
            'canvas_id' => Canvas::factory()->create(['organisation_id' => $this->organisation->id])->id,
            'name' => 'Elders',
        ]);
        $unknown = UnknownAllocationDomain::ensure($this->canvas);

        foreach ([$actors, $archived, $elsewhere, $unknown] as $target) {
            $this->replace([$this->line($target, 10_000)])
                ->assertSessionHasErrors(['rule' => 'Doel '.$target->name.' kan geen kosten dragen']);
        }

        $this->assertSame(0, AllocationSet::count());
    }

    public function test_a_cost_record_without_yearly_figures_in_the_year_is_refused(): void
    {
        $this->replace([$this->line($this->first, 10_000)], $this->costRecord())
            ->assertSessionHasErrors(['rule' => 'Geen jaarwaarden in 2026 voor deze kostenpost']);

        $this->assertSame(0, AllocationSet::count());
    }

    public function test_an_archived_canvas_or_organisation_is_refused(): void
    {
        $this->canvas->forceFill(['archived_at' => now()])->save();
        $this->replace([$this->line($this->first, 10_000)])->assertSessionHasErrors(['rule' => 'Dit canvas is gearchiveerd']);

        $this->canvas->forceFill(['archived_at' => null])->save();
        $this->organisation->forceFill(['archived_at' => now()])->save();
        $this->replace([$this->line($this->first, 10_000)])->assertSessionHasErrors(['rule' => 'Deze organisatie is gearchiveerd']);

        $this->assertSame(0, AllocationSet::count());
    }

    public function test_a_year_finalised_in_this_canvas_is_refused_and_one_finalised_elsewhere_is_not(): void
    {
        $other = Canvas::factory()->create(['organisation_id' => $this->organisation->id]);
        FinalisedYear::factory()->create(['canvas_id' => $other->id, 'fiscal_year_id' => $this->year->id]);

        $this->replace([$this->line($this->first, 10_000)])->assertSessionHasNoErrors();

        FinalisedYear::factory()->create(['canvas_id' => $this->canvas->id, 'fiscal_year_id' => $this->year->id]);

        $this->replace([$this->line($this->second, 10_000)])
            ->assertSessionHasErrors(['rule' => '2026 is definitief. Heropen het jaar eerst']);

        $this->assertSame([['Offreren', 10_000]], $this->stored());
    }

    public function test_an_archived_cost_record_is_still_allocatable(): void
    {
        $this->record->forceFill(['archived_at' => now()])->save();

        $this->replace([$this->line($this->first, 10_000)])->assertSessionHasNoErrors();

        $this->assertSame([['Offreren', 10_000]], $this->stored());
    }

    public function test_the_route_is_for_administrators_only(): void
    {
        $this->actingAs(User::factory()->create())
            ->put("/canvases/{$this->canvas->id}/years/2026/cost-records/{$this->record->id}/allocation", ['lines' => []])
            ->assertForbidden();

        $this->assertSame(0, AllocationSet::count());
    }
}
