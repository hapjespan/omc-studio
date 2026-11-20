<?php

namespace Tests\Feature\Layout;

use App\Models\AuditEntry;
use App\Models\Element;
use App\Models\User;
use App\Services\Structure\ElementTree;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Tests\Feature\Structure\StructureTestCase;

class LayoutAuditTest extends StructureTestCase
{
    private User $administrator;

    private Element $domain;

    private Element $activity;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->administrator = User::factory()->administrator()->create();
        $this->domain = $this->domain();
        $this->activity = $this->activity($this->domain);
    }

    public function test_a_save_writes_one_entry_per_changed_element_and_area_with_old_and_new_values(): void
    {
        $this->save(['areas' => ['primary_activities' => ['x' => 0, 'y' => 180, 'width' => 900, 'height' => 400]], 'elements' => [(string) $this->domain->id => $this->box(['style' => ['fill' => '#F6E6CC']])]]);

        $entries = $this->entries();

        $this->assertCount(2, $entries);

        $element = $entries->firstWhere('subject_type', 'element');
        $this->assertSame('layout_changed', $element->action);
        $this->assertSame($this->canvas->id, $element->canvas_id);
        $this->assertSame($this->administrator->id, $element->actor_id);
        $this->assertSame([null, ['x' => 0, 'y' => 0, 'z' => 0]], $element->changes['place']);
        $this->assertSame([null, ['width' => 200, 'height' => 100]], $element->changes['size']);
        $this->assertSame([null, ['fill' => '#F6E6CC']], $element->changes['style']);

        $area = $entries->firstWhere('subject_type', 'area');
        $this->assertSame($this->canvas->id, $area->canvas_id);
        $this->assertSame('primary_activities', $area->subject_label);
    }

    public function test_an_element_that_did_not_change_gets_no_entry(): void
    {
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);
        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(), (string) $this->activity->id => $this->box()]]);

        $this->assertSame(1, $this->entries()->where('subject_id', (string) $this->domain->id)->count());
        $this->assertSame(1, $this->entries()->where('subject_id', (string) $this->activity->id)->count());
    }

    public function test_changes_by_the_same_user_within_sixty_seconds_merge_into_one_entry(): void
    {
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);
        Carbon::setTestNow(Carbon::now()->addSeconds(59));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 10])]]);
        Carbon::setTestNow(Carbon::now()->addSeconds(59));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 30, 'width' => 300])]]);

        $entries = $this->entries();

        $this->assertCount(1, $entries);
        $this->assertSame([null, ['x' => 30, 'y' => 0, 'z' => 0]], $entries[0]->changes['place']);
        $this->assertSame([null, ['width' => 300, 'height' => 100]], $entries[0]->changes['size']);
    }

    public function test_a_merge_keeps_the_oldest_old_value(): void
    {
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);
        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 10])]]);
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 20])]]);

        $latest = $this->entries()->last();

        $this->assertCount(2, $this->entries());
        $this->assertSame([['x' => 0, 'y' => 0, 'z' => 0], ['x' => 20, 'y' => 0, 'z' => 0]], $latest->changes['place']);
    }

    public function test_a_change_after_sixty_seconds_is_a_new_entry(): void
    {
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);
        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 10])]]);

        $this->assertCount(2, $this->entries());
    }

    public function test_a_merge_that_returns_to_the_old_value_leaves_no_entry(): void
    {
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);
        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 10])]]);
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);

        $this->assertCount(1, $this->entries());
    }

    public function test_entries_are_never_merged_across_another_change_of_the_element(): void
    {
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);
        app(ElementTree::class)->update($this->domain->fresh(), ['name' => 'Nieuwe naam']);
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 10])]]);

        $this->assertCount(2, $this->entries()->where('action', 'layout_changed'));
    }

    public function test_another_user_does_not_merge(): void
    {
        $this->save(['elements' => [(string) $this->domain->id => $this->box()]]);
        $this->administrator = User::factory()->administrator()->create();
        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->save(['elements' => [(string) $this->domain->id => $this->box(['x' => 10])]]);

        $this->assertCount(2, $this->entries());
    }

    /** @return Collection<int, AuditEntry> */
    private function entries()
    {
        return AuditEntry::query()->where('action', 'layout_changed')->orderBy('id')->get()->values();
    }

    /** @param array<string, mixed> $values */
    private function save(array $values): void
    {
        $this->canvas->refresh();

        $this->actingAs($this->administrator)->putJson('/canvases/'.$this->canvas->id.'/layout', [
            'structure_version' => (int) $this->canvas->structure_version,
            'layout_version' => (int) $this->canvas->layout_version,
            'format_version' => 1,
            'areas' => $values['areas'] ?? [],
            'elements' => array_replace([
                (string) $this->domain->id => ['mode' => 'automatic'],
                (string) $this->activity->id => ['mode' => 'automatic'],
            ], $values['elements'] ?? []),
        ])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function box(array $values = []): array
    {
        return array_replace(['mode' => 'manual', 'x' => 0, 'y' => 0, 'width' => 200, 'height' => 100], $values);
    }
}
