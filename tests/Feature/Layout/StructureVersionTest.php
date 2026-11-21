<?php

namespace Tests\Feature\Layout;

use App\Models\AuditEntry;
use App\Models\Element;
use App\Models\User;
use App\Services\Structure\ElementArchive;
use App\Services\Structure\ElementDeletion;
use App\Services\Undo\UndoStore;
use Tests\Feature\Structure\StructureTestCase;

class StructureVersionTest extends StructureTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_creating_an_element_raises_the_version_once(): void
    {
        $before = $this->version();

        $domain = $this->domain();

        $this->assertSame($before + 1, $this->version());

        $this->activity($domain);

        $this->assertSame($before + 2, $this->version());
    }

    public function test_moving_an_element_raises_the_version_once(): void
    {
        $first = $this->domain('primary_activities', 'Inkoop');
        $second = $this->domain('primary_activities', 'Verkoop');
        $activity = $this->activity($first);
        $before = $this->version();

        $this->tree()->move($activity, $second);

        $this->assertSame($second->id, $activity->fresh()->parent_id);
        $this->assertSame($before + 1, $this->version());
    }

    public function test_the_parent_select_move_through_the_route_raises_the_version_once(): void
    {
        $first = $this->domain('primary_activities', 'Inkoop');
        $second = $this->domain('primary_activities', 'Verkoop');
        $activity = $this->activity($first);
        $before = $this->version();

        $this->actingAs($this->administrator)
            ->patch("/canvases/{$this->canvas->id}/elements/{$activity->id}", ['parent_id' => $second->id])
            ->assertRedirect();

        $this->assertSame($second->id, $activity->fresh()->parent_id);
        $this->assertSame($before + 1, $this->version());
    }

    public function test_a_reorder_raises_the_version_once_and_a_no_op_leaves_it_alone(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'Een');
        $this->activity($domain, 'Twee');
        $before = $this->version();

        $this->tree()->reorder($first, 'up');

        $this->assertSame($before, $this->version());

        $this->tree()->reorder($first, 'down');

        $this->assertSame($before + 1, $this->version());
    }

    public function test_archiving_and_restoring_each_raise_the_version_once(): void
    {
        $domain = $this->domain();
        $this->activity($domain, 'Een');
        $before = $this->version();

        app(ElementArchive::class)->archive($domain);

        $this->assertSame($before + 1, $this->version());

        app(ElementArchive::class)->restore($domain);

        $this->assertSame($before + 2, $this->version());
    }

    public function test_deleting_a_subtree_raises_the_version_once_and_the_undo_raises_it_again(): void
    {
        $domain = $this->domain();
        $this->activity($domain, 'Een');
        $this->activity($domain, 'Twee');
        $before = $this->version();

        $token = app(ElementDeletion::class)->delete($domain, $this->administrator);

        $this->assertSame(0, Element::count());
        $this->assertSame($before + 1, $this->version());

        app(UndoStore::class)->restore($token, $this->administrator);

        $this->assertSame(3, Element::count());
        $this->assertSame($before + 2, $this->version());
    }

    public function test_a_quick_entry_of_several_elements_raises_the_version_once(): void
    {
        $before = $this->version();

        $this->actingAs($this->administrator)
            ->post("/canvases/{$this->canvas->id}/quick-entry", ['area' => 'primary_activities', 'text' => "Inkoop\n  Offreren\nVerkoop"])
            ->assertRedirect();

        $this->assertSame(3, Element::count());
        $this->assertSame($before + 1, $this->version());
    }

    public function test_a_quick_entry_that_creates_nothing_leaves_the_version_alone(): void
    {
        $this->domain('primary_activities', 'Inkoop');
        $before = $this->version();

        $this->actingAs($this->administrator)
            ->post("/canvases/{$this->canvas->id}/quick-entry", ['area' => 'primary_activities', 'text' => 'Inkoop'])
            ->assertRedirect();

        $this->assertSame(1, Element::count());
        $this->assertSame($before, $this->version());
    }

    public function test_renaming_an_element_leaves_the_version_alone(): void
    {
        $domain = $this->domain();
        $before = $this->version();

        $this->tree()->update($domain, ['name' => 'Anders']);

        $this->assertSame('Anders', $domain->fresh()->name);
        $this->assertSame($before, $this->version());
    }

    public function test_a_raise_writes_no_audit_entry_for_the_canvas(): void
    {
        $domain = $this->domain();
        $entries = AuditEntry::query()->where('subject_type', 'canvas')->count();

        app(ElementArchive::class)->archive($domain);
        app(ElementArchive::class)->restore($domain);

        $this->assertSame($entries, AuditEntry::query()->where('subject_type', 'canvas')->count());
    }

    private function version(): int
    {
        return (int) $this->canvas->fresh()->structure_version;
    }
}
