<?php

namespace Tests\Feature\Structure;

use App\Models\Element;
use App\Models\User;
use App\Services\Structure\ElementDeletion;
use App\Services\Undo\UndoStore;

class ElementDeletionTest extends StructureTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    private function deletion(): ElementDeletion
    {
        return app(ElementDeletion::class);
    }

    public function test_a_leaf_is_removed(): void
    {
        $domain = $this->domain();

        $this->deletion()->delete($domain, $this->administrator);

        $this->assertSame(0, Element::count());
    }

    public function test_a_domain_goes_with_its_activities_and_the_undo_restores_all(): void
    {
        $domain = $this->domain();
        $this->activity($domain, 'Een');
        $this->activity($domain, 'Twee');
        $ids = Element::pluck('id')->sort()->values()->all();

        $token = $this->deletion()->delete($domain, $this->administrator);

        $this->assertSame(0, Element::count());

        app(UndoStore::class)->restore($token, $this->administrator);

        $this->assertSame($ids, Element::pluck('id')->sort()->values()->all());
        $this->assertSame('Een', Element::where('name', 'Een')->firstOrFail()->name);
    }

    public function test_a_nested_subtree_is_restored_with_parents_before_children(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain);
        $this->activity($cluster);

        $token = $this->deletion()->delete($domain, $this->administrator);
        app(UndoStore::class)->restore($token, $this->administrator);

        $this->assertSame(3, Element::count());
        $this->assertSame($cluster->id, Element::where('kind', 'activity')->firstOrFail()->parent_id);
    }

    public function test_siblings_stay(): void
    {
        $domain = $this->domain();
        $other = $this->domain('primary_activities', 'Anders');

        $this->deletion()->delete($domain, $this->administrator);

        $this->assertSame([$other->id], Element::pluck('id')->all());
    }

    public function test_an_archived_canvas_refuses_and_nothing_changes(): void
    {
        $domain = $this->domain();
        $this->canvas->update(['archived_at' => now()]);

        $message = $this->refusal(fn () => $this->deletion()->delete($domain, $this->administrator));

        $this->assertSame('Dit canvas is gearchiveerd', $message);
        $this->assertSame(1, Element::count());
    }
}
