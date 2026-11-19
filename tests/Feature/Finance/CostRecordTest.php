<?php

namespace Tests\Feature\Finance;

use App\Enums\CostKind;
use App\Models\Application;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CostRecordTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Owner $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->owner = Owner::factory()->create(['organisation_id' => $this->organisation->id]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge(['code' => 'LC-001', 'name' => 'Licentie planning', 'cost_kind' => 'supplier_contract', 'supplier' => 'Leverancier X', 'owner_id' => $this->owner->id, 'cost_centre' => '4100', 'description' => 'Omschrijving'], $overrides);
    }

    /** @param array<string, mixed> $overrides
     * @return TestResponse<Response> */
    private function store(array $overrides = []): TestResponse
    {
        return $this->admin()->post("/organisations/{$this->organisation->id}/cost-records", $this->payload($overrides));
    }

    public function test_a_cost_record_is_created_with_every_field(): void
    {
        $application = Application::factory()->create(['organisation_id' => $this->organisation->id]);

        $this->store(['application_id' => $application->id])->assertSessionHasNoErrors();

        $record = CostRecord::firstOrFail();
        $this->assertSame('LC-001', $record->code);
        $this->assertSame(CostKind::SupplierContract, $record->cost_kind);
        $this->assertSame($application->id, $record->application_id);
        $this->assertSame('4100', $record->cost_centre);
    }

    public function test_name_cost_kind_and_owner_are_required_and_kind_is_checked(): void
    {
        $this->store(['name' => ' ', 'cost_kind' => '', 'owner_id' => null])->assertSessionHasErrors(['name', 'cost_kind', 'owner_id']);
        $this->store(['cost_kind' => 'bogus'])->assertSessionHasErrors('cost_kind');
        $this->assertSame(0, CostRecord::count());
    }

    public function test_a_duplicate_code_is_refused_ignoring_case_and_empty_codes_repeat(): void
    {
        $this->store()->assertSessionHasNoErrors();
        $this->store(['code' => 'lc-001', 'name' => 'Twee'])->assertSessionHasErrors(['code' => 'Code: deze code bestaat al in deze organisatie']);

        $this->store(['code' => '', 'name' => 'Drie'])->assertSessionHasNoErrors();
        $this->store(['code' => null, 'name' => 'Vier'])->assertSessionHasNoErrors();

        $this->assertSame(3, CostRecord::count());
    }

    public function test_the_same_code_is_allowed_in_another_organisation(): void
    {
        $this->store()->assertSessionHasNoErrors();
        $other = Organisation::factory()->create();
        $owner = Owner::factory()->create(['organisation_id' => $other->id]);

        $this->admin()->post("/organisations/{$other->id}/cost-records", $this->payload(['owner_id' => $owner->id]))->assertSessionHasNoErrors();

        $this->assertSame(2, CostRecord::count());
    }

    public function test_owner_and_application_must_belong_to_the_organisation(): void
    {
        $foreignOwner = Owner::factory()->create();
        $foreignApplication = Application::factory()->create();

        $this->store(['owner_id' => $foreignOwner->id])->assertSessionHasErrors('owner_id');
        $this->store(['application_id' => $foreignApplication->id])->assertSessionHasErrors('application_id');
        $this->assertSame(0, CostRecord::count());
    }

    public function test_lengths_follow_fr_015(): void
    {
        $this->store(['name' => str_repeat('a', 151)])->assertSessionHasErrors('name');
        $this->store(['code' => str_repeat('a', 51)])->assertSessionHasErrors('code');
        $this->store(['cost_centre' => str_repeat('a', 51)])->assertSessionHasErrors('cost_centre');
        $this->store(['supplier' => str_repeat('a', 151)])->assertSessionHasErrors('supplier');
        $this->store(['description' => str_repeat('a', 2001)])->assertSessionHasErrors('description');
        $this->assertSame(0, CostRecord::count());
    }

    public function test_an_update_keeps_its_own_code_and_refuses_another_cost_records_code(): void
    {
        $this->store()->assertSessionHasNoErrors();
        $this->store(['code' => 'LC-002', 'name' => 'Twee'])->assertSessionHasNoErrors();
        $record = CostRecord::where('code', 'LC-001')->firstOrFail();

        $this->admin()->patch("/cost-records/{$record->id}", $this->payload(['name' => 'Nieuw']))->assertSessionHasNoErrors();
        $this->assertSame('Nieuw', $record->fresh()->name);

        $this->admin()->patch("/cost-records/{$record->id}", $this->payload(['code' => 'lc-002']))->assertSessionHasErrors('code');
    }

    public function test_an_archived_organisation_is_refused(): void
    {
        $this->organisation->update(['archived_at' => now()]);

        $this->store()->assertSessionHasErrors('rule');
        $this->assertSame(0, CostRecord::count());
    }

    public function test_a_reader_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->post("/organisations/{$this->organisation->id}/cost-records", $this->payload())->assertForbidden();
    }
}
