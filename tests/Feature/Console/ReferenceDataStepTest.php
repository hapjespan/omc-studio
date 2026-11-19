<?php

namespace Tests\Feature\Console;

use App\Models\Area;
use App\Models\Modality;
use App\Services\Operations\InstallSteps;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_runs_leave_exactly_eight_areas_and_four_modalities(): void
    {
        $step = new ReferenceDataStep;
        $step();
        $step();

        $this->assertSame(8, Area::count());
        $this->assertSame(4, Modality::count());
    }

    public function test_the_areas_carry_key_kind_order_and_cost_flag(): void
    {
        (new ReferenceDataStep)();

        $this->assertSame(Area::KEYS, Area::orderBy('position')->pluck('key')->all());
        $this->assertSame(
            ['direction' => 'text', 'steering' => 'activities', 'governing' => 'activities', 'actors' => 'list', 'channels' => 'list', 'value_propositions' => 'list', 'primary_activities' => 'activities', 'supporting_activities' => 'activities'],
            Area::pluck('kind', 'key')->all(),
        );
        $this->assertSame(
            ['steering', 'governing', 'primary_activities', 'supporting_activities'],
            Area::where('carries_costs', true)->orderBy('position')->pluck('key')->all(),
        );
    }

    public function test_the_modalities_have_their_letters_and_colours(): void
    {
        (new ReferenceDataStep)();

        $this->assertSame(
            ['common' => ['C', '#1F7A4D'], 'adaptive' => ['A', '#1E5FBF'], 'specialized' => ['S', '#B4540A'], 'distinct' => ['D', '#6B3FB5']],
            Modality::orderBy('position')->get()->mapWithKeys(fn ($m) => [$m->key => [$m->letter, $m->colour]])->all(),
        );
    }

    public function test_a_changed_row_is_restored_on_the_next_run(): void
    {
        $step = new ReferenceDataStep;
        $step();
        Modality::where('key', 'common')->update(['letter' => 'X']);
        $step();

        $this->assertSame('C', Modality::where('key', 'common')->value('letter'));
    }

    public function test_install_runs_the_step(): void
    {
        $this->app->make(InstallSteps::class)->run();

        $this->assertSame(8, Area::count());
    }
}
