<?php

namespace Tests\Feature\Exchange;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class ExchangeTestCase extends TestCase
{
    use RefreshDatabase;

    protected Organisation $organisation;

    protected Canvas $canvas;

    protected FiscalYear $year;

    protected Owner $owner;

    protected Element $domain;

    protected Element $activity;

    protected function setUp(): void
    {
        parent::setUp();

        app(ReferenceDataStep::class)();
        $this->organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id]);
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
        $this->owner = Owner::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ada']);
        $this->domain = $this->element('primary_activities', ElementKind::Domain, 'Inkoop');
        $this->activity = $this->element('primary_activities', ElementKind::Activity, 'Offreren', $this->domain);
        UnknownAllocationDomain::ensure($this->canvas);
        $this->actingAs(User::factory()->administrator()->create());
    }

    protected function element(string $area, ElementKind $kind, string $name, ?Element $parent = null, ?Canvas $canvas = null): Element
    {
        return Element::factory()->create([
            'canvas_id' => ($canvas ?? $this->canvas)->id,
            'area_id' => Area::query()->where('key', $area)->firstOrFail()->id,
            'kind' => $kind,
            'parent_id' => $parent?->id,
            'name' => $name,
        ]);
    }

    protected function costRecord(?string $code, string $name = 'Licentie', ?int $budget = 1_000): CostRecord
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'owner_id' => $this->owner->id, 'code' => $code, 'name' => $name]);
        $budget !== null && YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => $budget]);

        return $record;
    }

    /**
     * @param  list<string>  $lines
     */
    protected function upload(string $header, array $lines, string $url): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', implode("\n", [$header, ...$lines])."\n");

        return $this->post($url, ['file' => $file], ['Accept' => 'application/json']);
    }
}
