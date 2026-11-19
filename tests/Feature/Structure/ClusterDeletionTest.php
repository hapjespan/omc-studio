<?php

namespace Tests\Feature\Structure;

use App\Models\Element;
use App\Models\User;
use App\Services\Structure\ElementDeletion;

class ClusterDeletionTest extends StructureTestCase
{
    private User $administrator;

    private Element $domain;

    private Element $cluster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->domain = $this->domain();
        $this->cluster = $this->cluster($this->domain, 'Groep');
    }

    private function deleteCluster(string $choice): void
    {
        app(ElementDeletion::class)->delete($this->cluster, $this->administrator, $choice);
    }

    public function test_deleting_the_activities_too_removes_the_cluster_and_its_activities(): void
    {
        $this->activity($this->cluster, 'Een');

        $this->deleteCluster('delete');

        $this->assertSame(1, Element::count());
    }

    public function test_lifting_keeps_the_activities_under_the_domain_with_identifier_and_fields(): void
    {
        $activity = $this->activity($this->cluster, 'Een');
        $activity->update(['description' => 'Tekst']);
        $existing = $this->activity($this->domain, 'Bestaand');

        $this->deleteCluster('lift');

        $lifted = $activity->fresh();
        $this->assertSame($this->domain->id, $lifted->parent_id);
        $this->assertSame('Tekst', $lifted->description);
        $this->assertSame($existing->position + 1, $lifted->position);
        $this->assertNull($this->cluster->fresh());
    }

    public function test_lifting_is_refused_naming_the_activities_when_the_domain_holds_the_same_name(): void
    {
        $this->activity($this->domain, 'Werving');
        $this->activity($this->cluster, 'werving');
        $this->activity($this->cluster, 'Vrij');

        $message = $this->refusal(fn () => $this->deleteCluster('lift'));

        $this->assertSame('Terugzetten kan niet: werving staat al onder het domein', $message);
        $this->assertNotNull($this->cluster->fresh());
        $this->assertSame(5, Element::count());
    }

    public function test_a_cluster_with_activities_needs_a_choice(): void
    {
        $this->activity($this->cluster, 'Een');

        $message = $this->refusal(fn () => app(ElementDeletion::class)->delete($this->cluster, $this->administrator));

        $this->assertSame('Kies wat er met de activiteiten van het cluster gebeurt', $message);
        $this->assertSame(3, Element::count());
    }

    public function test_an_empty_cluster_needs_no_choice(): void
    {
        app(ElementDeletion::class)->delete($this->cluster, $this->administrator);

        $this->assertSame(1, Element::count());
    }
}
