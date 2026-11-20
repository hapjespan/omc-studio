<?php

namespace Tests\Feature\Layout;

use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use App\Services\Structure\ElementArchive;
use App\Services\Structure\ElementDeletion;
use App\Services\Undo\UndoStore;
use Tests\Feature\Structure\StructureTestCase;

class LayoutLifecycleTest extends StructureTestCase
{
    private ?User $user = null;

    public function test_archiving_an_element_drops_its_row_and_raises_the_layout_version(): void
    {
        $domain = $this->domain();
        $this->place($domain);
        $before = $this->version();

        app(ElementArchive::class)->archive($domain);

        $this->assertSame(0, Layout::query()->where('element_id', $domain->id)->count());
        $this->assertSame($before + 1, $this->version());
    }

    public function test_archiving_a_domain_drops_the_rows_of_its_whole_subtree_and_raises_the_version_once(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);
        $activity = $this->activity($cluster);
        $other = $this->domain('primary_activities', 'Verkoop');

        foreach ([$domain, $cluster, $activity, $other] as $element) {
            $this->place($element);
        }

        $before = $this->version();

        app(ElementArchive::class)->archive($domain);

        $this->assertSame([$other->id], Layout::query()->pluck('element_id')->all());
        $this->assertSame($before + 1, $this->version());
    }

    public function test_archiving_an_element_without_a_row_leaves_the_layout_version_alone(): void
    {
        $domain = $this->domain();
        $before = $this->version();

        app(ElementArchive::class)->archive($domain);

        $this->assertSame($before, $this->version());
    }

    public function test_an_element_restored_from_archive_gets_no_row(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain);
        $this->place($activity);

        app(ElementArchive::class)->archive($domain);

        $before = $this->version();

        app(ElementArchive::class)->restore($domain);

        $this->assertSame(0, Layout::query()->count());
        $this->assertSame($before, $this->version());
    }

    public function test_deleting_a_subtree_drops_its_rows_and_the_undo_restores_them_with_their_place_and_style(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain);
        $this->place($domain);
        $this->place($activity, 120);

        $before = $this->version();

        $token = app(ElementDeletion::class)->delete($domain, $this->administrator());

        $this->assertSame(0, Layout::query()->count());
        $this->assertSame($before + 1, $this->version());

        app(UndoStore::class)->restore($token, $this->administrator());

        $restored = Layout::query()->where('element_id', $activity->id)->sole();

        $this->assertSame(2, Layout::query()->count());
        $this->assertSame(120, $restored->x);
        $this->assertSame(['fill' => '#1B4D7A'], $restored->style);
        $this->assertSame($before + 2, $this->version());
    }

    public function test_an_undo_of_a_deletion_without_rows_leaves_the_layout_version_alone(): void
    {
        $domain = $this->domain();
        $before = $this->version();

        $token = app(ElementDeletion::class)->delete($domain, $this->administrator());

        app(UndoStore::class)->restore($token, $this->administrator());

        $this->assertSame($before, $this->version());
    }

    public function test_lifting_the_children_of_a_deleted_cluster_drops_their_rows_with_the_cluster_row(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);
        $activity = $this->activity($cluster);

        foreach ([$domain, $cluster, $activity] as $element) {
            $this->place($element);
        }

        $before = $this->version();

        app(ElementDeletion::class)->delete($cluster, $this->administrator(), 'lift');

        $this->assertSame($domain->id, $activity->fresh()->parent_id);
        $this->assertSame([$domain->id], Layout::query()->pluck('element_id')->all());
        $this->assertGreaterThan($before, $this->version());
    }

    public function test_an_undo_of_a_lifting_deletion_restores_only_the_cluster_row(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);
        $activity = $this->activity($cluster);
        $this->place($cluster);
        $this->place($activity);

        $token = app(ElementDeletion::class)->delete($cluster, $this->administrator(), 'lift');

        app(UndoStore::class)->restore($token, $this->administrator());

        $this->assertSame([$cluster->id], Layout::query()->pluck('element_id')->all());
    }

    private function administrator(): User
    {
        return $this->user ??= User::factory()->administrator()->create();
    }

    private function place(Element $element, int $x = 40): Layout
    {
        return Layout::create([
            'canvas_id' => $element->canvas_id,
            'element_id' => $element->id,
            'x' => $x,
            'y' => 24,
            'width' => 200,
            'height' => 80,
            'z' => 0,
            'style' => ['fill' => '#1B4D7A'],
        ]);
    }

    private function version(): int
    {
        return (int) $this->canvas->fresh()->layout_version;
    }
}
