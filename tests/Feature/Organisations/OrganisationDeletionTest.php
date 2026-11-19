<?php

namespace Tests\Feature\Organisations;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\UndoRecord;
use App\Services\Canvas\OrganisationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganisationDeletionTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganisationService::class);
    }

    private function organisationWithLogo(string $name): Organisation
    {
        $organisation = $this->service->create($name);
        $path = 'logos/'.$organisation->slug.'.png';
        Storage::disk('local')->put($path, 'image-bytes');
        $organisation->forceFill(['logo_path' => $path])->save();

        return $organisation;
    }

    public function test_deletion_is_refused_while_the_organisation_still_holds_a_canvas(): void
    {
        $organisation = $this->service->create('Noord');
        Canvas::factory()->create(['organisation_id' => $organisation->id, 'archived_at' => now()]);
        $entries = AuditEntry::query()->count();

        try {
            $this->service->delete($organisation);
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Deze organisatie heeft nog canvassen of kostenposten. Archiveer haar, of verwijder die eerst', $e->errors()['rule'][0]);
        }

        $this->assertSame(1, Organisation::query()->count());
        $this->assertSame($entries, AuditEntry::query()->count());
    }

    public function test_deleting_an_empty_organisation_leaves_one_entry_with_its_name_and_counts(): void
    {
        $other = $this->service->create('Zuid');
        $organisation = $this->service->create('Noord');
        $this->service->rename($organisation, 'Noord 2027');
        $elsewhere = AuditEntry::query()->count() - AuditEntry::where('organisation_id', $organisation->id)->count();

        $this->service->delete($organisation);

        $entries = AuditEntry::where('organisation_id', $organisation->id)->get();

        $this->assertSame(0, Organisation::whereKey($organisation->id)->count());
        $this->assertCount(1, $entries);
        $this->assertSame('deleted', $entries[0]->action);
        $this->assertSame('organisation', $entries[0]->subject_type);
        $this->assertSame('Noord 2027', $entries[0]->subject_label);
        $this->assertSame(['name' => ['Noord 2027', null], 'audit_entries' => [2, null]], $entries[0]->changes);
        $this->assertSame($elsewhere, AuditEntry::where('organisation_id', $other->id)->count());
    }

    public function test_deletion_offers_no_undo(): void
    {
        $organisation = $this->service->create('Noord');

        $this->service->delete($organisation);

        $this->assertSame(0, UndoRecord::query()->count());
    }

    public function test_an_archived_organisation_is_read_only_and_can_be_restored(): void
    {
        $organisation = $this->service->create('Noord');

        $this->service->archive($organisation);

        try {
            $this->service->rename($organisation, 'Nieuw');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Deze organisatie is gearchiveerd', $e->errors()['rule'][0]);
        }

        $this->service->restore($organisation);
        $this->service->rename($organisation, 'Nieuw');

        $this->assertNull($organisation->fresh()->archived_at);
        $this->assertSame('Nieuw', $organisation->fresh()->name);
    }

    public function test_archiving_and_restoring_are_audited_under_their_own_action(): void
    {
        $organisation = $this->service->create('Noord');

        $this->service->archive($organisation);
        $this->service->restore($organisation);

        $actions = AuditEntry::where('organisation_id', $organisation->id)->orderBy('id')->pluck('action')->all();

        $this->assertSame(['created', 'archived', 'restored'], $actions);
    }

    public function test_archiving_an_archived_organisation_and_restoring_an_active_one_change_nothing(): void
    {
        $organisation = $this->service->create('Noord');
        $this->service->archive($organisation);
        $archivedAt = $organisation->fresh()->archived_at;

        $this->service->archive($organisation);
        $this->assertEquals($archivedAt, $organisation->fresh()->archived_at);

        $this->service->restore($organisation);
        $this->service->restore($organisation);

        $actions = AuditEntry::where('organisation_id', $organisation->id)->orderBy('id')->pluck('action')->all();

        $this->assertSame(['created', 'archived', 'restored'], $actions);
    }

    public function test_the_stored_logo_goes_with_the_deletion_and_stays_on_a_refusal(): void
    {
        Storage::fake('local');

        $kept = $this->organisationWithLogo('Zuid');
        Canvas::factory()->create(['organisation_id' => $kept->id]);
        $removed = $this->organisationWithLogo('Noord');

        try {
            $this->service->delete($kept);
            $this->fail('expected refusal');
        } catch (ValidationException) {
        }

        $this->service->delete($removed);

        Storage::disk('local')->assertExists($kept->logo_path);
        Storage::disk('local')->assertMissing($removed->logo_path);
    }

    public function test_an_archived_organisation_without_canvases_is_deleted(): void
    {
        $organisation = $this->service->create('Noord');
        $this->service->archive($organisation);

        $this->service->delete($organisation);

        $this->assertSame(0, Organisation::query()->count());
        $this->assertSame(1, AuditEntry::where('organisation_id', $organisation->id)->count());
    }
}
