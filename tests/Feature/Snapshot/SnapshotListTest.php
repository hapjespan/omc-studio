<?php

namespace Tests\Feature\Snapshot;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\User;
use App\Services\Operations\Steps\BlankTemplateStep;
use App\Services\Snapshot\Snapshots;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Allocation\AllocationTestCase;

class SnapshotListTest extends AllocationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->allocate($this->costRecord('Licentie', 2026, 10_000), 2026, [$this->target()->id => 10000]);
        $this->actingAs($this->admin());
        $snapshots = app(Snapshots::class);
        $snapshots->take($this->canvas, $this->fiscalYear(2026), 'Eerste', 'Voor de stuurgroep');
        $snapshots->archive($snapshots->take($this->canvas, $this->fiscalYear(2026), 'Tweede'));
    }

    public function test_the_administrator_lists_the_live_snapshots_without_their_content(): void
    {
        $this->get("/canvases/{$this->canvas->id}/versions")->assertInertia(fn (Assert $page) => $page
            ->component('Versions/Index')
            ->where('canWrite', true)
            ->where('years', [2026])
            ->where('count', 1)
            ->where('rows.0.name', 'Eerste')
            ->where('rows.0.fiscal_year', 2026)
            ->where('rows.0.remark', 'Voor de stuurgroep')
            ->where('rows.0.imported', false)
            ->has('rows.0.maker')
            ->has('rows.0.created')
            ->missing('rows.0.content'));
    }

    public function test_archived_snapshots_show_on_request(): void
    {
        $this->get("/canvases/{$this->canvas->id}/versions?archived=1")->assertInertia(fn (Assert $page) => $page->where('count', 2));
    }

    public function test_a_reader_lists_but_cannot_write(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get("/canvases/{$this->canvas->id}/versions")->assertInertia(fn (Assert $page) => $page->where('canWrite', false)->where('count', 1));
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        auth()->logout();

        $this->get("/canvases/{$this->canvas->id}/versions")->assertRedirect();
    }

    public function test_a_template_has_no_versions_page(): void
    {
        (new BlankTemplateStep)();
        $template = Canvas::where('kind', CanvasKind::Template->value)->firstOrFail();

        $this->get("/canvases/{$template->id}/versions")->assertNotFound();
    }
}
