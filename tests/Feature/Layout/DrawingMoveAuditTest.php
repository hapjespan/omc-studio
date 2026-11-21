<?php

namespace Tests\Feature\Layout;

use App\Models\AuditEntry;
use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\Structure\StructureTestCase;

class DrawingMoveAuditTest extends StructureTestCase
{
    private User $administrator;

    private Element $source;

    private Element $target;

    private Element $activity;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->administrator = User::factory()->administrator()->create();
        $this->source = $this->domain('primary_activities', 'Inkoop');
        $this->target = $this->domain('primary_activities', 'Verkoop');
        $this->activity = $this->activity($this->source);
    }

    public function test_a_move_is_audited_with_the_old_and_new_hangt_onder_and_the_layout_of_the_block(): void
    {
        $this->move(['x' => 4, 'y' => 40, 'width' => 160, 'height' => 48, 'z' => 0]);

        $entries = AuditEntry::query()->where('subject_id', (string) $this->activity->id)->where('action', '!=', 'created')->orderBy('id')->get();
        $parent = $entries->first(fn (AuditEntry $entry) => array_key_exists('parent_id', $entry->changes));
        $layout = $entries->firstWhere('action', 'layout_changed');

        $this->assertSame([$this->source->id, $this->target->id], array_map(fn ($value) => (string) $value, $parent->changes['parent_id']));
        $this->assertNotNull($layout);
        $this->assertSame([160, 48], [$layout->changes['size'][1]['width'], $layout->changes['size'][1]['height']]);
    }

    public function test_a_layout_entry_before_the_move_is_never_merged_into_the_one_after_it(): void
    {
        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->activity->id, 'x' => 1, 'y' => 1, 'width' => 100, 'height' => 30, 'z' => 0, 'style' => []]);
        $this->canvas->refresh();
        $this->actingAs($this->administrator)->putJson("/canvases/{$this->canvas->id}/layout", $this->savePayload(['x' => 2, 'y' => 2, 'width' => 100, 'height' => 30]))->assertOk();
        Carbon::setTestNow('2026-11-20 10:00:20');

        $this->move(['x' => 9, 'y' => 9, 'width' => 100, 'height' => 30, 'z' => 0]);

        $entries = AuditEntry::query()->where('subject_id', (string) $this->activity->id)->where('action', 'layout_changed')->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame([1, 1, 0], array_values($entries[0]->changes['place'][0]));
        $this->assertSame([9, 9, 0], array_values($entries[1]->changes['place'][1]));
    }

    public function test_an_undo_to_automatic_audits_the_parent_change_and_the_removed_layout(): void
    {
        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->activity->id, 'x' => 4, 'y' => 4, 'width' => 100, 'height' => 30, 'z' => 0, 'style' => []]);

        $this->move(null);

        $this->assertNull(Layout::query()->where('element_id', $this->activity->id)->first());
        $entries = AuditEntry::query()->where('subject_id', (string) $this->activity->id)->get();
        $this->assertTrue($entries->contains(fn (AuditEntry $entry) => array_key_exists('parent_id', $entry->changes)));
        $this->assertTrue($entries->contains(fn (AuditEntry $entry) => $entry->action === 'layout_changed' && array_key_exists('place', $entry->changes) && $entry->changes['place'][1] === null));
    }

    /** @param ?array<string, int> $layout */
    private function move(?array $layout): void
    {
        $this->canvas->refresh();
        $this->actingAs($this->administrator)->postJson("/canvases/{$this->canvas->id}/drawing/moves", [
            'element_id' => (string) $this->activity->id,
            'target_id' => (string) $this->target->id,
            'structure_version' => (int) $this->canvas->structure_version,
            'layout_version' => (int) $this->canvas->layout_version,
            'layout' => $layout,
        ])->assertOk();
    }

    /**
     * @param  array<string, int>  $box
     * @return array<string, mixed>
     */
    private function savePayload(array $box): array
    {
        return [
            'structure_version' => (int) $this->canvas->structure_version,
            'layout_version' => (int) $this->canvas->layout_version,
            'format_version' => 1,
            'areas' => [],
            'elements' => [(string) $this->source->id => ['mode' => 'automatic'], (string) $this->target->id => ['mode' => 'automatic'], (string) $this->activity->id => ['mode' => 'manual', 'z' => 0, ...$box]],
        ];
    }
}
