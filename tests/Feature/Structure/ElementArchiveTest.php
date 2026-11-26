<?php

namespace Tests\Feature\Structure;

use App\Models\Element;
use App\Services\Structure\ElementArchive;

class ElementArchiveTest extends StructureTestCase
{
    private function archive(): ElementArchive
    {
        return app(ElementArchive::class);
    }

    public function test_archiving_hides_the_element_and_its_subtree(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain, 'Groep');
        $activity = $this->activity($cluster);

        $this->archive()->archive($domain);

        $this->assertNotNull($domain->fresh()->archived_at);
        $this->assertNotNull($cluster->fresh()->archived_at);
        $this->assertNotNull($activity->fresh()->archived_at);
        $this->assertSame(0, Element::query()->whereNull('archived_at')->count());
    }

    public function test_restoring_returns_the_subtree_at_the_end_of_the_order(): void
    {
        $domain = $this->domain('primary_activities', 'Eerste');
        $activity = $this->activity($domain);
        $this->archive()->archive($domain);
        $later = $this->domain('primary_activities', 'Tweede');

        $this->archive()->restore($domain);

        $this->assertNull($domain->fresh()->archived_at);
        $this->assertNull($activity->fresh()->archived_at);
        $this->assertSame($later->position + 1, $domain->fresh()->position);
    }

    public function test_an_earlier_archived_child_stays_archived_on_restore(): void
    {
        $domain = $this->domain();
        $old = $this->activity($domain, 'Oud');
        $this->archive()->archive($old);
        $this->travel(1)->minutes();
        $this->archive()->archive($domain);

        $this->archive()->restore($domain);

        $this->assertNotNull($old->fresh()->archived_at);
    }

    public function test_restore_is_refused_when_the_parent_is_archived(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain);
        $this->archive()->archive($activity);
        $this->archive()->archive($domain);

        $message = $this->refusal(fn () => $this->archive()->restore($activity));

        $this->assertSame('Terugzetten kan niet: het bovenliggende element is gearchiveerd', $message);
        $this->assertNotNull($activity->fresh()->archived_at);
    }

    public function test_restore_is_refused_when_the_name_is_taken_again(): void
    {
        $domain = $this->domain();
        $activity = $this->activity($domain, 'Werving');
        $this->archive()->archive($activity);
        $this->activity($domain, 'werving');

        $message = $this->refusal(fn () => $this->archive()->restore($activity));

        $this->assertSame('Naam: er staat al een element met deze naam op deze plek', $message);
        $this->assertNotNull($activity->fresh()->archived_at);
    }

    public function test_an_archived_canvas_refuses(): void
    {
        $domain = $this->domain();
        $this->canvas->update(['archived_at' => now()]);

        $this->assertSame('Dit canvas is gearchiveerd', $this->refusal(fn () => $this->archive()->archive($domain)));
        $this->assertNull($domain->fresh()->archived_at);
    }
}
