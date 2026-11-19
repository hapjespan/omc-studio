<?php

namespace Tests\Feature\Operations;

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Operations\InstallSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SampleDataRemoveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(InstallSteps::class)->run();
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_the_page_reports_whether_the_sample_exists(): void
    {
        $this->admin()->get('/admin/sample-data')->assertInertia(fn (Assert $page) => $page->component('Admin/SampleData')->where('present', true)->where('name', 'Voorbeeld BV'));
    }

    public function test_removal_deletes_everything_and_a_restart_does_not_bring_it_back(): void
    {
        $this->admin()->post('/admin/sample-data/remove')->assertRedirect('/admin/sample-data');

        $this->assertSame(0, Organisation::where('is_sample', true)->count());
        $this->assertSame(0, CostRecord::count());
        $this->assertSame(0, Canvas::where('kind', 'canvas')->count());
        $this->assertSame(0, UndoRecord::count());
        $this->assertTrue(DB::table('application_state')->where('key', 'sample_data_removed')->exists());

        $this->app->make(InstallSteps::class)->run();

        $this->assertSame(0, Organisation::count());
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
}
