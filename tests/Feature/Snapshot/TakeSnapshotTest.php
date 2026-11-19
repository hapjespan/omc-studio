<?php

namespace Tests\Feature\Snapshot;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Snapshot;
use App\Models\SystemNotice;
use App\Models\User;
use App\Services\Snapshot\SnapshotContent;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Allocation\AllocationTestCase;

class TakeSnapshotTest extends AllocationTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->admin();
        $this->allocate($this->costRecord('Licentie', 2026, 10_000), 2026, [$this->target()->id => 10000]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function take(array $data = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->administrator)
            ->postJson("/canvases/{$this->canvas->id}/versions", [...['name' => 'Voor de stuurgroep', 'fiscal_year' => 2026], ...$data]);
    }

    private function skewByOneCent(): void
    {
        $record = $this->costRecord('Scheef', 2026, 10_000);
        $domain = $this->domain('primary_activities', 'Scheef domein');
        $kept = $this->activity($domain, 'Blijft');
        $dropped = $this->activity($domain, 'Verdwijnt');
        $this->allocate($record, 2026, [$kept->id => 9999, $dropped->id => 1]);

        Element::whereKey($dropped->id)->update(['archived_at' => now()]);
    }

    public function test_an_administrator_takes_a_snapshot_of_a_year_that_is_not_finalised(): void
    {
        $this->take(['remark' => 'Versie voor de begrotingsronde'])->assertRedirect();

        $snapshot = Snapshot::sole();
        $this->assertSame($this->canvas->id, $snapshot->canvas_id);
        $this->assertSame('Voor de stuurgroep', $snapshot->name);
        $this->assertSame(2026, $snapshot->fiscal_year);
        $this->assertSame('Versie voor de begrotingsronde', $snapshot->remark);
        $this->assertSame($this->administrator->id, $snapshot->created_by);
        $this->assertEqualsWithDelta(now()->timestamp, $snapshot->created_at->timestamp, 5);
        $this->assertFalse($snapshot->imported);
        $this->assertNull($snapshot->archived_at);
        $this->assertSame(SnapshotContent::SCHEMA_VERSION, $snapshot->schema_version);
        $this->assertSame(app(SnapshotContent::class)->checksum($snapshot->content), $snapshot->checksum);

        $content = json_decode($snapshot->content, true);
        $this->assertSame(10_000, $content['figures']['canvasTotal']['budget']);
        $this->assertTrue($content['figures']['closingCheck']['passed']);
    }

    public function test_a_snapshot_without_a_remark_is_allowed(): void
    {
        $this->take()->assertRedirect();

        $this->assertNull(Snapshot::sole()->remark);
    }

    public function test_the_snapshot_is_audited_without_its_content(): void
    {
        $this->take()->assertRedirect();

        $snapshot = Snapshot::sole();
        $this->assertDatabaseHas('audit_entries', [
            'subject_type' => 'snapshot',
            'subject_id' => (string) $snapshot->id,
            'action' => 'created',
            'actor_id' => $this->administrator->id,
            'canvas_id' => $this->canvas->id,
        ]);

        $entry = AuditEntry::where('subject_type', 'snapshot')->sole();
        $this->assertArrayNotHasKey('content', $entry->changes);
        $this->assertSame([null, 'Voor de stuurgroep'], $entry->changes['name']);
    }

    public function test_a_one_cent_discrepancy_refuses_the_snapshot_and_stores_nothing(): void
    {
        $this->skewByOneCent();

        $this->take()->assertUnprocessable()
            ->assertJsonPath('errors.rule.0', 'De cijfers sluiten niet; een momentopname kan niet');

        $this->assertSame(0, Snapshot::count());
    }

    public function test_the_failing_closing_check_records_a_system_notice(): void
    {
        $this->skewByOneCent();

        $this->take()->assertUnprocessable();

        $notice = SystemNotice::sole();
        $this->assertSame('closing_check_failed', $notice->kind);
        $this->assertSame($this->canvas->id, $notice->canvas_id);
        $this->assertSame(2026, $notice->details['year']);
        $this->assertSame('budget', $notice->details['check']);
        $this->assertSame(-1, $notice->details['differenceCents']);
    }

    public function test_a_reader_may_not_take_a_snapshot(): void
    {
        $this->take([], User::factory()->create())->assertForbidden();

        $this->assertSame(0, Snapshot::count());
    }

    public function test_an_anonymous_request_is_refused(): void
    {
        $this->postJson("/canvases/{$this->canvas->id}/versions", ['name' => 'Naam', 'fiscal_year' => 2026])->assertUnauthorized();
    }

    public function test_a_name_outside_one_to_hundred_and_fifty_characters_is_refused(): void
    {
        $this->take(['name' => ' '])->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Naam: vul een naam in van 1 tot 150 tekens');
        $this->take(['name' => str_repeat('a', 151)])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame(0, Snapshot::count());
    }

    public function test_the_fiscal_year_must_belong_to_the_organisation_of_the_canvas(): void
    {
        $other = Canvas::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $this->fiscalYear(2030, $other->organisation_id);

        $this->take(['fiscal_year' => 2030])->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        $this->take(['fiscal_year' => 2031])->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');

        $this->assertSame(0, Snapshot::count());
    }

    public function test_an_archived_canvas_refuses_a_snapshot(): void
    {
        $this->canvas->update(['archived_at' => now()]);

        $this->take()->assertUnprocessable()->assertJsonValidationErrors('rule');

        $this->assertSame(0, Snapshot::count());
    }
}
