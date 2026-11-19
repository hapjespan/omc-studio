<?php

namespace Tests\Feature\Organisations;

use App\Http\Requests\OrganisationRequest;
use App\Models\AuditEntry;
use App\Models\Organisation;
use App\Services\Canvas\OrganisationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganisationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganisationService::class);
    }

    public function test_create_stores_a_trimmed_name_and_a_slug_and_an_audit_entry(): void
    {
        $organisation = $this->service->create('  Stichting Noord & Zuid ');

        $this->assertSame('Stichting Noord & Zuid', $organisation->name);
        $this->assertSame('stichting-noord-zuid', $organisation->slug);
        $this->assertSame(1, AuditEntry::where('subject_type', 'organisation')->where('action', 'created')->count());
    }

    public function test_a_name_differing_in_case_or_surrounding_spaces_is_refused(): void
    {
        $this->service->create('Stichting Noord');

        try {
            $this->service->create('stichting noord ');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Naam: er bestaat al een organisatie met deze naam', $e->errors()['name'][0]);
        }

        $this->assertSame(1, Organisation::count());
    }

    public function test_slugs_stay_unique_when_names_differ_only_in_punctuation(): void
    {
        $first = $this->service->create('Noord!');
        $second = $this->service->create('Noord?');

        $this->assertSame('noord', $first->slug);
        $this->assertSame('noord-2', $second->slug);
    }

    public function test_rename_keeps_the_slug_and_may_change_only_the_case_of_its_own_name(): void
    {
        $organisation = $this->service->create('Noord');

        $this->service->rename($organisation, 'NOORD');
        $this->assertSame('NOORD', $organisation->fresh()->name);
        $this->assertSame('noord', $organisation->fresh()->slug);

        $this->assertSame(1, AuditEntry::where('subject_type', 'organisation')->where('action', 'changed')->count());
    }

    public function test_rename_to_the_name_of_another_is_refused(): void
    {
        $this->service->create('Zuid');
        $organisation = $this->service->create('Noord');

        $this->expectException(ValidationException::class);

        $this->service->rename($organisation, ' zuid');
    }

    public function test_an_archived_organisation_cannot_be_renamed(): void
    {
        $organisation = Organisation::factory()->create(['archived_at' => now()]);

        try {
            $this->service->rename($organisation, 'Nieuw');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Deze organisatie is gearchiveerd', $e->errors()['rule'][0]);
        }
    }

    public function test_the_request_requires_a_name_of_at_most_150_characters(): void
    {
        $rules = (new OrganisationRequest)->rules();

        $this->assertTrue(Validator::make(['name' => 'Noord'], $rules)->passes());
        $this->assertTrue(Validator::make(['name' => ''], $rules)->fails());
        $this->assertTrue(Validator::make(['name' => str_repeat('a', 151)], $rules)->fails());
    }
}
