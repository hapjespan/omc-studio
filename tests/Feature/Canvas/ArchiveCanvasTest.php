<?php

namespace Tests\Feature\Canvas;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\User;
use App\Services\Canvas\CanvasLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ArchiveCanvasTest extends TestCase
{
    use RefreshDatabase;

    private CanvasLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = app(CanvasLifecycle::class);
    }

    public function test_rename_changes_the_name_and_audits_old_and_new(): void
    {
        $this->actingAs(User::factory()->create());
        $canvas = Canvas::factory()->create(['name' => 'Oud']);

        $this->lifecycle->rename($canvas, ' Nieuw ');

        $this->assertSame('Nieuw', $canvas->fresh()->name);
        $entry = AuditEntry::where('subject_type', 'canvas')->where('action', 'changed')->latest('id')->firstOrFail();
        $this->assertSame(['Oud', 'Nieuw'], $entry->changes['name']);
        $this->assertNotNull($entry->actor_id);
    }

    public function test_rename_to_a_taken_name_is_refused_but_its_own_case_may_change(): void
    {
        $organisation = Canvas::factory()->create(['name' => 'Een'])->organisation;
        $other = Canvas::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Twee']);

        $this->lifecycle->rename($other, 'TWEE');
        $this->assertSame('TWEE', $other->fresh()->name);

        try {
            $this->lifecycle->rename($other, ' een');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Naam: dit canvas bestaat al in deze organisatie', $e->errors()['name'][0]);
        }
    }

    public function test_archive_sets_the_stamp_and_audits_archived(): void
    {
        $canvas = Canvas::factory()->create();

        $this->lifecycle->archive($canvas);

        $this->assertNotNull($canvas->fresh()->archived_at);
        $this->assertSame(1, AuditEntry::where('subject_type', 'canvas')->where('action', 'archived')->count());
    }

    public function test_archive_twice_audits_once(): void
    {
        $canvas = Canvas::factory()->create();

        $this->lifecycle->archive($canvas);
        $this->lifecycle->archive($canvas->fresh());

        $this->assertSame(1, AuditEntry::where('action', 'archived')->count());
    }

    public function test_an_archived_canvas_refuses_rename(): void
    {
        $canvas = Canvas::factory()->create(['archived_at' => now()]);

        try {
            $this->lifecycle->rename($canvas, 'Anders');
            $this->fail('expected refusal');
        } catch (ValidationException $e) {
            $this->assertSame('Dit canvas is gearchiveerd', $e->errors()['rule'][0]);
        }
    }

    public function test_restore_clears_the_stamp_and_audits_restored(): void
    {
        $canvas = Canvas::factory()->create(['archived_at' => now()]);

        $this->lifecycle->restore($canvas);

        $this->assertNull($canvas->fresh()->archived_at);
        $this->assertSame(1, AuditEntry::where('subject_type', 'canvas')->where('action', 'restored')->count());
    }

    public function test_a_template_is_refused_by_every_operation(): void
    {
        $template = Canvas::factory()->template()->create();

        foreach (['rename' => ['X'], 'archive' => [], 'restore' => []] as $method => $arguments) {
            try {
                $this->lifecycle->{$method}($template, ...$arguments);
                $this->fail($method.' should be refused');
            } catch (ValidationException $e) {
                $this->assertSame('Een sjabloon bevat alleen structuur en Koers', $e->errors()['rule'][0]);
            }
        }
    }
}
