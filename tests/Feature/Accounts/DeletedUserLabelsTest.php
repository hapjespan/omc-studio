<?php

namespace Tests\Feature\Accounts;

use App\Models\FinalisedYear;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Accounts\UserDeletion;
use App\Services\Snapshot\Snapshots;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Allocation\AllocationTestCase;

class DeletedUserLabelsTest extends AllocationTestCase
{
    public function test_a_finalised_year_of_a_deleted_user_names_the_deleted_user(): void
    {
        $maker = User::factory()->administrator()->create();
        $this->finalise(2026)->update(['finalised_by' => $maker->id]);
        $administrator = $this->admin();
        $this->actingAs($administrator);
        app(UserDeletion::class)->delete($maker, $administrator);

        $this->get("/organisations/{$this->canvas->organisation_id}/cost-records")->assertInertia(fn (Assert $page) => $page
            ->where('finalised.0.by', __('users.deleted_user')));
    }

    public function test_an_imported_finalised_year_names_nobody(): void
    {
        $this->finalise(2026)->update(['imported' => true]);

        $this->actingAs($this->admin())->get("/organisations/{$this->canvas->organisation_id}/cost-records")->assertInertia(fn (Assert $page) => $page
            ->where('finalised.0.by', null));
        $this->assertTrue(FinalisedYear::query()->firstOrFail()->imported);
    }

    public function test_a_snapshot_of_a_deleted_user_names_the_deleted_user_and_an_imported_one_names_nobody(): void
    {
        $maker = User::factory()->administrator()->create();
        $this->actingAs($maker);
        $snapshots = app(Snapshots::class);
        $snapshots->take($this->canvas, $this->fiscalYear(2026), 'Door ex');
        $snapshots->take($this->canvas, $this->fiscalYear(2026), 'Geimporteerd');
        Snapshot::where('name', 'Geimporteerd')->update(['imported' => true, 'created_by' => null]);
        $administrator = $this->admin();
        $this->actingAs($administrator);
        app(UserDeletion::class)->delete($maker, $administrator);

        $this->get("/canvases/{$this->canvas->id}/versions")->assertInertia(fn (Assert $page) => $page
            ->where('rows', fn ($rows) => array_column($rows->toArray(), 'maker', 'name') == ['Door ex' => __('users.deleted_user'), 'Geimporteerd' => null]));
    }
}
