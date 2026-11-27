<?php

namespace Tests\Feature\Concurrency;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Services\Operations\Steps\ReferenceDataStep;
use App\Services\Structure\ElementTree;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RaceDatabase;
use Tests\TestCase;

abstract class RaceTestCase extends TestCase
{
    use RefreshDatabase;

    protected const YEAR = 2026;

    protected const CANVAS = 'Race canvas';

    protected const UNALLOCATED = 'Hosting';

    protected const TABLES = [
        'system_notices', 'snapshots', 'audit_entries', 'undo_records', 'finalised_years', 'allocation_lines', 'allocation_sets',
        'yearly_figures', 'cost_records', 'applications', 'owners', 'fiscal_years', 'elements',
        'statements', 'canvases', 'organisations', 'users',
    ];

    /** @var array<string, string> */
    protected array $environment;

    protected Canvas $canvas;

    protected FiscalYear $year;

    protected CostRecord $unallocated;

    private RaceDatabase $race;

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = new RaceDatabase;
        $this->environment = $this->race->prepare();

        $this->onRace(function (): void {
            app(ReferenceDataStep::class)();

            $organisation = Organisation::factory()->create();
            $this->canvas = Canvas::factory()->create(['organisation_id' => $organisation->id, 'name' => self::CANVAS]);
            $this->year = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => self::YEAR]);

            $tree = app(ElementTree::class);
            $area = Area::where('key', 'primary_activities')->firstOrFail();
            $domain = $tree->create($this->canvas, $area, ElementKind::Domain, null, ['name' => 'Inkoop']);
            $activity = $tree->create($this->canvas, $area, ElementKind::Activity, $domain->id, ['name' => 'Offreren']);

            $allocated = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Licentie']);
            YearlyFigure::factory()->create(['cost_record_id' => $allocated->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 100_000]);

            $set = AllocationSet::create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $allocated->id, 'fiscal_year_id' => $this->year->id]);
            AllocationLine::create(['allocation_set_id' => $set->id, 'element_id' => $activity->id, 'basis_points' => 10_000, 'position' => 0]);

            $this->unallocated = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'name' => self::UNALLOCATED]);
        });
    }

    protected function tearDown(): void
    {
        $this->race->clean(self::TABLES);

        parent::tearDown();
    }

    protected function emptyOrganisations(): void
    {
        foreach (array_diff(self::TABLES, ['users']) as $table) {
            DB::connection('race')->table($table)->delete();
        }
    }

    protected function onRace(Closure $work): void
    {
        $previous = DB::getDefaultConnection();

        DB::setDefaultConnection('race');

        try {
            $work();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }
}
