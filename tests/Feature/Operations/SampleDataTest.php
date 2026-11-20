<?php

namespace Tests\Feature\Operations;

use App\Enums\ElementKind;
use App\Enums\StatementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Statement;
use App\Services\Finance\FigureService;
use App\Services\Operations\InstallSteps;
use App\Services\Operations\SampleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SampleDataTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'organisations', 'canvases', 'statements', 'elements', 'owners', 'applications',
        'fiscal_years', 'cost_records', 'yearly_figures', 'allocation_sets', 'allocation_lines',
    ];

    private function install(): void
    {
        $this->app->make(InstallSteps::class)->run();
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return array_combine(self::TABLES, array_map(fn (string $table) => DB::table($table)->count(), self::TABLES));
    }

    private function sampleCanvas(): Canvas
    {
        return Canvas::where('organisation_id', Organisation::where('is_sample', true)->sole()->id)->sole();
    }

    public function test_the_sample_flag_is_indexed_and_defaults_to_false(): void
    {
        $this->assertFalse(Organisation::factory()->create()->fresh()->is_sample);
        $this->assertTrue(collect(Schema::getIndexes('organisations'))->contains(fn (array $index) => $index['columns'] === ['is_sample']));
    }

    public function test_the_install_creates_the_marked_sample_organisation_with_one_filled_canvas(): void
    {
        $this->install();

        $organisation = Organisation::where('is_sample', true)->sole();
        $canvas = $this->sampleCanvas();

        $this->assertSame('Voorbeeld BV', $organisation->name);
        $this->assertTrue($organisation->is_sample);
        $this->assertSame(1, Statement::where('canvas_id', $canvas->id)->where('kind', StatementKind::Mission->value)->count());
        $this->assertSame(1, Statement::where('canvas_id', $canvas->id)->where('kind', StatementKind::Vision->value)->count());
        $this->assertGreaterThan(0, Statement::where('canvas_id', $canvas->id)->where('kind', StatementKind::Goal->value)->count());
    }

    public function test_every_area_of_the_sample_canvas_holds_its_own_kind_of_structure(): void
    {
        $this->install();

        $canvas = $this->sampleCanvas();

        foreach (Area::where('kind', 'activities')->get() as $area) {
            $domains = Element::where('canvas_id', $canvas->id)->where('area_id', $area->id)
                ->where('kind', ElementKind::Domain->value)->where('is_unknown_allocation', false)->get();

            $this->assertGreaterThanOrEqual(2, $domains->count(), $area->key);

            foreach ($domains as $domain) {
                $branch = Element::where('parent_id', $domain->id)->pluck('id')->all();

                $this->assertNotEmpty($branch, $area->key.' '.$domain->name);
                $this->assertGreaterThan(0, Element::whereIn('parent_id', [$domain->id, ...$branch])
                    ->where('kind', ElementKind::Activity->value)->count(), $area->key.' '.$domain->name);
            }
        }

        foreach (Area::where('kind', 'list')->get() as $area) {
            $groups = Element::where('canvas_id', $canvas->id)->where('area_id', $area->id)->where('kind', ElementKind::Domain->value)->get();

            $this->assertGreaterThan(0, $groups->count(), $area->key);
            $this->assertGreaterThan(0, Element::whereIn('parent_id', $groups->modelKeys())->where('kind', ElementKind::Activity->value)->count(), $area->key);
        }

        $this->assertGreaterThan(0, Element::where('canvas_id', $canvas->id)->where('kind', ElementKind::Cluster->value)->count());
        $this->assertGreaterThan(0, Element::where('canvas_id', $canvas->id)->whereNotNull('modality_id')->count());
    }

    public function test_ten_cost_records_of_the_current_year_close_to_the_cent_with_two_in_unknown_allocation(): void
    {
        $this->install();

        $canvas = $this->sampleCanvas();
        $year = (int) now()->year;
        $organisation = $canvas->organisation;

        $this->assertSame(10, DB::table('cost_records')->where('organisation_id', $organisation->id)->count());
        $this->assertSame(1, DB::table('fiscal_years')->where('organisation_id', $organisation->id)->where('year', $year)->count());
        $this->assertSame(10, DB::table('yearly_figures')->count());

        $figures = $this->app->make(FigureService::class)->figures($canvas, $year);

        $this->assertNotNull($figures);
        $this->assertSame(8, $figures->sidePanel['allocated']['count']);
        $this->assertSame(2, $figures->sidePanel['unknown']['count']);
        $this->assertSame(
            (int) DB::table('yearly_figures')->sum('budget_cents'),
            $figures->sidePanel['allocated']['budget'] + $figures->sidePanel['unknown']['budget'],
        );
        $this->assertTrue($figures->closingCheck['passed']);
    }

    public function test_a_second_start_adds_nothing(): void
    {
        $this->install();
        $first = $this->counts();

        $this->install();

        $this->assertSame($first, $this->counts());
        $this->assertSame(1, Organisation::count());
    }

    public function test_nothing_is_seeded_once_the_sample_data_was_removed(): void
    {
        DB::table('application_state')->insert(['key' => SampleData::REMOVED_KEY, 'value' => '1']);

        $this->install();

        $this->assertSame(0, Organisation::count());
        $this->assertSame(0, Element::count());
    }
}
