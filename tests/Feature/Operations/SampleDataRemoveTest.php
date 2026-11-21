<?php

namespace Tests\Feature\Operations;

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Operations\InstallSteps;
use App\Services\Operations\SampleData;
use App\Services\Operations\Steps\SampleDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\ActsAsAdministrator;
use Tests\TestCase;

class SampleDataRemoveTest extends TestCase
{
    use ActsAsAdministrator;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(InstallSteps::class)->run();
    }

    private const TABLES = [
        'organisations', 'canvases', 'statements', 'elements', 'owners', 'applications',
        'fiscal_years', 'cost_records', 'yearly_figures', 'allocation_sets', 'allocation_lines',
    ];

    public function test_the_page_reports_whether_the_sample_exists(): void
    {
        $this->admin()->get('/admin/sample-data')->assertInertia(fn (Assert $page) => $page->component('Admin/SampleData')->where('present', true)->where('name', 'Voorbeeld BV'));
    }

    public function test_removal_deletes_everything_and_a_restart_does_not_bring_it_back(): void
    {
        $other = Organisation::factory()->create();
        $otherCanvas = Canvas::factory()->create(['organisation_id' => $other->id]);
        $otherCost = CostRecord::factory()->create(['organisation_id' => $other->id]);
        $otherOwner = Owner::factory()->create(['organisation_id' => $other->id]);

        foreach (self::TABLES as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table);
        }

        $this->admin()->post('/admin/sample-data/remove')->assertRedirect('/admin/sample-data');

        foreach (['canvases', 'statements', 'elements', 'applications', 'fiscal_years', 'yearly_figures', 'allocation_sets', 'allocation_lines'] as $table) {
            $this->assertSame($table === 'canvases' ? 2 : 0, DB::table($table)->count(), $table);
        }

        $this->assertNotNull($otherCanvas->fresh());
        $this->assertNotNull($otherCost->fresh());
        $this->assertNotNull($otherOwner->fresh());

        $this->assertSame(0, Organisation::where('is_sample', true)->count());
        $this->assertSame(1, CostRecord::count());
        $this->assertSame(0, UndoRecord::count());
        $this->assertTrue(DB::table('application_state')->where('key', 'sample_data_removed')->exists());

        $this->app->make(InstallSteps::class)->run();

        $this->assertSame(1, Organisation::count());
        $this->admin()->get('/admin/sample-data')->assertInertia(fn (Assert $page) => $page->where('present', false));
    }

    public function test_other_organisations_are_left_alone(): void
    {
        $other = Organisation::factory()->create();

        $this->admin()->post('/admin/sample-data/remove')->assertRedirect();

        $this->assertNotNull($other->fresh());
    }

    public function test_a_reader_gets_403(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'reader']))->post('/admin/sample-data/remove')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'reader']))->get('/admin/sample-data')->assertForbidden();
        $this->assertSame(1, Organisation::where('is_sample', true)->count());
    }

    public function test_a_second_submit_is_a_no_op_redirect(): void
    {
        $this->admin()->post('/admin/sample-data/remove')->assertRedirect('/admin/sample-data');
        $this->admin()->post('/admin/sample-data/remove')->assertRedirect('/admin/sample-data');

        $this->assertSame(1, DB::table('application_state')->where('key', 'sample_data_removed')->count());
    }

    public function test_an_archived_sample_refuses_removal_with_the_rule_message(): void
    {
        Organisation::where('is_sample', true)->first()->update(['archived_at' => now()]);

        $this->admin()->post('/admin/sample-data/remove')->assertSessionHasErrors('rule');

        $this->assertSame(1, Organisation::where('is_sample', true)->count());
        $this->assertFalse(DB::table('application_state')->where('key', 'sample_data_removed')->exists());
    }

    public function test_a_failing_sample_install_does_not_stop_the_install(): void
    {
        DB::table('application_state')->delete();
        $this->mock(SampleData::class)->shouldReceive('install')->andThrow(new RuntimeException('boom'));

        $this->app->make(SampleDataStep::class)();

        $this->assertFalse(DB::table('application_state')->where('key', 'sample_data_removed')->exists());
    }

    public function test_nothing_is_seeded_on_an_installation_that_already_has_an_organisation(): void
    {
        $this->app->make(SampleData::class)->remove(User::factory()->administrator()->create());
        DB::table('application_state')->delete();
        Organisation::query()->delete();
        Organisation::factory()->create(['name' => 'Voorbeeld BV']);

        $this->app->make(SampleData::class)->install();

        $this->assertSame(1, Organisation::count());
        $this->assertSame(0, Organisation::where('is_sample', true)->count());
    }
}
