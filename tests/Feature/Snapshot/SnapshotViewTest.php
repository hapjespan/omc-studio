<?php

namespace Tests\Feature\Snapshot;

use App\Models\Element;
use App\Models\Snapshot;
use App\Models\SystemNotice;
use App\Models\User;
use App\Services\Snapshot\Snapshots;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Allocation\AllocationTestCase;

class SnapshotViewTest extends AllocationTestCase
{
    private Snapshot $snapshot;

    private Element $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocate($this->costRecord('Licentie', 2026, 10_000), 2026, [($this->target = $this->target())->id => 10000]);
        $this->actingAs($this->admin());
        $this->snapshot = app(Snapshots::class)->take($this->canvas, $this->fiscalYear(2026), 'Voor de stuurgroep');
    }

    public function test_a_reader_sees_the_stored_elements_and_figures_after_the_live_canvas_changed(): void
    {
        $target = $this->target;
        $target->update(['name' => 'Hernoemd']);
        $target->update(['archived_at' => now()]);

        $this->actingAs(User::factory()->create())->get("/snapshots/{$this->snapshot->getKey()}/view?overlay=financial")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Canvas/SnapshotView')
                ->where('snapshot.name', 'Voor de stuurgroep')
                ->where('year', 2026)
                ->where('damaged', false)
                ->where('figuresMessage', null)
                ->where('figures.canvasTotal.budget', 10_000)
                ->where('elements', fn ($elements) => in_array('Doel', $elements->pluck('name')->all(), true) && ! in_array('Hernoemd', $elements->pluck('name')->all(), true))
                ->has('versions', 1)
                ->where('canvas.id', $this->canvas->id));
    }

    public function test_contributions_come_from_the_stored_figures_and_cost_records(): void
    {
        $element = $this->target->id;

        $this->get("/snapshots/{$this->snapshot->getKey()}/view?overlay=financial&element={$element}", ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Canvas/SnapshotView', 'X-Inertia-Partial-Data' => 'contributions', 'X-Inertia-Version' => (string) app(Middleware::class)->version(request())])
            ->assertJsonPath('props.contributions.0.name', 'Licentie')
            ->assertJsonPath('props.contributions.0.amountCents', 10_000);
    }

    public function test_figures_are_only_sent_for_the_financial_overlay(): void
    {
        $this->get("/snapshots/{$this->snapshot->getKey()}/view")->assertInertia(fn (Assert $page) => $page->where('figures', null)->where('overlay', 'none'));
    }

    public function test_a_tampered_snapshot_shows_the_damaged_message_with_no_figures_and_no_elements(): void
    {
        DB::table('snapshots')->where('id', $this->snapshot->getKey())->update(['content' => str_replace('"budget":10000', '"budget":99999', Snapshot::findOrFail($this->snapshot->getKey())->content)]);

        $this->get("/snapshots/{$this->snapshot->getKey()}/view?overlay=financial")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('damaged', true)
                ->where('figuresMessage', __('snap.damaged'))
                ->where('figures', null)
                ->where('elements', [])
                ->where('statements', []));

        $this->assertSame('snapshot_damaged', SystemNotice::sole()->kind);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        auth()->logout();

        $this->get("/snapshots/{$this->snapshot->getKey()}/view")->assertRedirect();
    }
}
