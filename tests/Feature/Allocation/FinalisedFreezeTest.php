<?php

namespace Tests\Feature\Allocation;

use App\Calculation\FigureCalculator;
use App\Calculation\Figures;
use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Modality;
use App\Models\YearlyFigure;
use App\Services\Allocation\AllocationSets;
use App\Services\Allocation\CarryOver;
use App\Services\Allocation\Redistribution;
use App\Services\Finance\CalculationInputBuilder;
use App\Services\Finance\FiscalYears;
use App\Services\Finance\YearlyFigures;

class FinalisedFreezeTest extends AllocationTestCase
{
    private const REFUSAL = '2026 is definitief. Heropen het jaar eerst';

    private function inCanvas(): string
    {
        return "2026 is definitief in canvas {$this->canvas->name}. Heropen het jaar eerst";
    }

    private function figures(?Canvas $canvas = null): Figures
    {
        return (new FigureCalculator)->calculate(app(CalculationInputBuilder::class)->build($canvas ?? $this->canvas, 2026));
    }

    private function figure(CostRecord $record, int $year): YearlyFigure
    {
        return YearlyFigure::with(['costRecord.organisation', 'fiscalYear'])
            ->where('cost_record_id', $record->getKey())
            ->where('fiscal_year_id', $this->fiscalYear($year)->id)
            ->firstOrFail();
    }

    public function test_saving_and_clearing_a_set_in_the_finalised_year_are_refused(): void
    {
        $record = $this->costRecord('Licentie', 2026);
        $activity = $this->target();
        $other = $this->target('Tweede');
        $this->allocate($record, 2026, [$activity->id => 10000]);
        $this->finalise(2026);

        $sets = app(AllocationSets::class);
        $year = FiscalYear::with('organisation')->whereKey($this->fiscalYear(2026)->id)->firstOrFail();

        $this->assertSame(self::REFUSAL, $this->refusal(fn () => $sets->replace($this->canvas, $record, $year, [['element_id' => $other->id, 'basis_points' => 10000]])));
        $this->assertSame(self::REFUSAL, $this->refusal(fn () => $sets->clear($this->canvas, $record, $year, $this->admin())));
        $this->assertSame(1, AllocationSet::where('fiscal_year_id', $year->id)->count());
        $this->assertSame([$activity->id], AllocationLine::pluck('element_id')->all());
    }

    public function test_sets_of_another_canvas_stay_writable_while_this_canvas_is_finalised(): void
    {
        $record = $this->costRecord('Licentie', 2026);
        $other = Canvas::factory()->create(['organisation_id' => $this->canvas->organisation_id, 'name' => 'Tweede']);
        $area = $this->area('primary_activities');
        $domain = $this->tree()->create($other, $area, ElementKind::Domain, null, ['name' => 'Inkoop']);
        $activity = $this->tree()->create($other, $area, ElementKind::Activity, (string) $domain->id, ['name' => 'Offreren']);
        $this->finalise(2026);

        $sets = app(AllocationSets::class);
        $year = FiscalYear::with('organisation')->whereKey($this->fiscalYear(2026)->id)->firstOrFail();

        $sets->replace($other, $record, $year, [['element_id' => $activity->id, 'basis_points' => 10000]]);

        $this->assertSame(1, AllocationSet::where('canvas_id', $other->id)->where('fiscal_year_id', $year->id)->count());

        $sets->clear($other, $record, $year, $this->admin());

        $this->assertSame(0, AllocationSet::where('canvas_id', $other->id)->count());
    }

    public function test_a_redistribution_touching_the_finalised_year_is_refused(): void
    {
        $activity = $this->activity($this->domain(), 'Offreren');
        $this->allocate($this->costRecord(), 2026, [$activity->id => 10000]);
        $this->finalise(2026);

        $redistribution = app(Redistribution::class);

        $this->assertSame(self::REFUSAL, $this->refusal(fn () => $redistribution->preview($activity)));
        $this->assertSame(self::REFUSAL, $this->refusal(fn () => $redistribution->apply($activity, [2026 => ['mode' => 'unknown']])));
        $this->assertSame(1, AllocationSet::where('fiscal_year_id', $this->fiscalYear(2026)->id)->count());
        $this->assertNull(Element::findOrFail($activity->id)->archived_at);
    }

    public function test_a_carry_over_into_the_finalised_year_is_refused_and_out_of_it_is_allowed(): void
    {
        $record = $this->costRecord('Licentie', 2026);
        $activity = $this->target('Doel');
        $this->allocate($record, 2025, [$activity->id => 10000]);
        $this->allocate($record, 2026, [$activity->id => 10000]);
        $this->fiscalYear(2027);
        $this->finalise(2026);

        $carryOver = app(CarryOver::class);
        $years = FiscalYear::with('organisation')->whereIn('year', [2025, 2026, 2027])->get()->keyBy('year');

        $this->assertSame($this->inCanvas(), $this->refusal(fn () => $carryOver->run($this->canvas, $years[2025], $years[2026], false)));

        $carryOver->run($this->canvas, $years[2026], $years[2027], true);

        $this->assertSame(1, AllocationSet::where('fiscal_year_id', $years[2027]->id)->count());
        $this->assertSame(1, YearlyFigure::where('fiscal_year_id', $years[2027]->id)->count());
    }

    public function test_yearly_figures_of_the_finalised_year_are_frozen_in_the_whole_organisation(): void
    {
        $allocated = $this->costRecord('Licentie', 2026);
        $this->allocate($allocated, 2026, [$this->target()->id => 10000]);
        $elsewhere = $this->costRecord('Hosting', 2026);
        $this->finalise(2026);

        $figures = app(YearlyFigures::class);
        $year = FiscalYear::with('organisation')->whereKey($this->fiscalYear(2026)->id)->firstOrFail();
        $open = $this->fiscalYear(2027);

        $this->assertSame($this->inCanvas(), $this->refusal(fn () => $figures->save($elsewhere, $year, ['budget' => '2500,00'])));
        $this->assertSame($this->inCanvas(), $this->refusal(fn () => $figures->save($this->costRecord('Nieuw', null), $year, ['budget' => '2500,00'])));
        $this->assertSame($this->inCanvas(), $this->refusal(fn () => $figures->delete($this->figure($allocated, 2026), $this->admin())));
        $this->assertSame($this->inCanvas(), $this->refusal(fn () => $figures->delete($this->figure($elsewhere, 2026), $this->admin())));
        $this->assertSame(2, YearlyFigure::where('fiscal_year_id', $year->id)->count());

        $figures->save($elsewhere, $open, ['budget' => '2500,00']);

        $this->assertSame(1, YearlyFigure::where('fiscal_year_id', $open->id)->count());
    }

    public function test_the_finalised_fiscal_year_itself_cannot_be_deleted(): void
    {
        $this->costRecord('Licentie', 2026);
        $this->finalise(2026);

        $year = FiscalYear::with('organisation')->whereKey($this->fiscalYear(2026)->id)->firstOrFail();

        $this->assertNotNull($this->refusal(fn () => app(FiscalYears::class)->delete($year)));
        $this->assertSame(1, FiscalYear::whereKey($year->id)->count());
    }

    public function test_sets_of_another_canvas_of_the_organisation_stay_writable(): void
    {
        $other = Canvas::factory()->create(['organisation_id' => $this->canvas->organisation_id, 'name' => 'Tweede']);
        $area = $this->area('primary_activities');
        $domain = $this->tree()->create($other, $area, ElementKind::Domain, null, ['name' => 'Inkoop']);
        $activity = $this->tree()->create($other, $area, ElementKind::Activity, (string) $domain->id, ['name' => 'Offreren']);
        $record = $this->costRecord('Licentie', 2026);
        $this->allocate($record, 2025, [$activity->id => 10000], $other->id);
        $this->finalise(2026);

        $years = FiscalYear::with('organisation')->whereIn('year', [2025, 2026])->get()->keyBy('year');

        app(CarryOver::class)->run($other, $years[2025], $years[2026], false);

        $this->assertSame(1, AllocationSet::where('canvas_id', $other->id)->where('fiscal_year_id', $years[2026]->id)->count());
    }

    public function test_elements_of_the_finalised_canvas_may_be_renamed_and_moved_and_the_canvas_total_holds(): void
    {
        $activity = $this->activity($this->domain('primary_activities', 'Inkoop'), 'Offreren');
        $supporting = $this->domain('supporting_activities', 'ICT');
        $this->allocate($this->costRecord('Licentie', 2026), 2026, [$activity->id => 10000]);
        $this->finalise(2026);

        $before = $this->figures();

        $this->tree()->update($activity, ['name' => 'Offertes maken']);
        $this->tree()->move($activity, $supporting);

        $after = $this->figures();

        $this->assertSame('Offertes maken', Element::findOrFail($activity->id)->name);
        $this->assertSame($supporting->area_id, Element::findOrFail($activity->id)->area_id);
        $this->assertSame(100_000, $before->canvasTotal['budget']);
        $this->assertSame($before->canvasTotal, $after->canvasTotal);
        $this->assertSame(100_000, $before->areas['primary_activities']['budget']);
        $this->assertSame(0, $after->areas['primary_activities']['budget']);
        $this->assertSame(100_000, $after->areas['supporting_activities']['budget']);
    }

    public function test_an_element_of_the_finalised_canvas_may_be_given_a_modality(): void
    {
        $this->allocate($this->costRecord('Licentie', 2026), 2026, [$this->target()->id => 10000]);
        $this->finalise(2026);

        $modality = Modality::where('key', 'common')->firstOrFail();
        $element = $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Domain, null, ['name' => 'Sturing', 'modality_id' => $modality->id]);

        $this->assertSame($modality->id, $element->modality_id);
        $this->assertSame(100_000, $this->figures()->breakdowns['modality']['none']['budget']);
    }
}
