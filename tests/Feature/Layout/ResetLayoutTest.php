<?php

namespace Tests\Feature\Layout;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Structure\StructureTestCase;

class ResetLayoutTest extends StructureTestCase
{
    private User $administrator;

    private Element $domain;

    private Element $cluster;

    private Element $activity;

    private Element $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->domain = $this->domain('primary_activities', 'Inkoop');
        $this->cluster = $this->cluster($this->domain);
        $this->activity = $this->activity($this->cluster);
        $this->other = $this->domain('primary_activities', 'Verkoop');

        foreach ([$this->domain, $this->cluster, $this->activity, $this->other] as $element) {
            Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $element->id, 'x' => 8, 'y' => 8, 'width' => 100, 'height' => 40, 'z' => 0, 'style' => []]);
        }

        Layout::create(['canvas_id' => $this->canvas->id, 'area_id' => $this->domain->area_id, 'x' => 0, 'y' => 180, 'width' => 900, 'height' => 400, 'z' => 0, 'style' => []]);
        $this->canvas->refresh();
    }

    public function test_the_element_scope_removes_only_its_row(): void
    {
        $this->reset(['scope' => 'element', 'element_id' => $this->cluster->id])->assertOk()->assertJsonPath('layout_version', 1);

        $this->assertNull(Layout::query()->where('element_id', $this->cluster->id)->first());
        $this->assertSame(4, Layout::query()->count());
    }

    public function test_the_domain_scope_removes_the_rows_of_the_domain_and_every_descendant(): void
    {
        $this->reset(['scope' => 'domain', 'element_id' => $this->domain->id])->assertOk();

        $this->assertEqualsCanonicalizing([$this->other->id], Layout::query()->whereNotNull('element_id')->pluck('element_id')->all());
        $this->assertSame(1, Layout::query()->whereNotNull('area_id')->count());
    }

    public function test_the_domain_scope_refuses_an_element_that_is_not_a_domain(): void
    {
        $this->reset(['scope' => 'domain', 'element_id' => $this->cluster->id])->assertUnprocessable()->assertJsonValidationErrors('rule');

        $this->assertSame(5, Layout::query()->count());
    }

    public function test_the_canvas_scope_removes_every_row_and_raises_the_version(): void
    {
        $this->reset(['scope' => 'canvas'])->assertOk()->assertJsonPath('layout_version', 1);

        $this->assertSame(0, Layout::query()->count());
        $this->assertSame(1, $this->canvas->fresh()->layout_version);
    }

    public function test_a_reset_is_audited_per_changed_element(): void
    {
        $this->reset(['scope' => 'domain', 'element_id' => $this->domain->id])->assertOk();

        $entries = AuditEntry::query()->where('action', 'layout_changed')->get();

        $this->assertCount(3, $entries);
        $this->assertSame([null], $entries->map(fn (AuditEntry $entry) => $entry->changes['place'][1])->unique()->values()->all());
    }

    public function test_an_element_of_another_canvas_is_not_found(): void
    {
        $foreign = Canvas::factory()->create();
        $foreignDomain = $this->domain('primary_activities', 'Elders', $foreign);

        $this->reset(['scope' => 'element', 'element_id' => $foreignDomain->id])->assertUnprocessable()->assertJsonValidationErrors('rule');
    }

    public function test_a_reader_is_refused_and_an_unknown_scope_is_invalid(): void
    {
        $this->actingAs(User::factory()->create())->postJson($this->url(), ['scope' => 'canvas'])->assertForbidden();
        $this->reset(['scope' => 'everything'])->assertUnprocessable();
        $this->assertSame(5, Layout::query()->count());
    }

    public function test_a_template_is_refused(): void
    {
        $template = Canvas::factory()->template()->create();

        $this->actingAs($this->administrator)->postJson('/canvases/'.$template->id.'/layout/reset', ['scope' => 'canvas'])->assertUnprocessable()->assertJsonValidationErrors('rule');
    }

    public function test_an_element_id_that_is_not_a_uuid_is_invalid(): void
    {
        $this->reset(['scope' => 'element', 'element_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('element_id');
    }

    public function test_an_archived_canvas_is_refused(): void
    {
        $this->canvas->update(['archived_at' => now()]);

        $this->reset(['scope' => 'canvas'])->assertUnprocessable();
        $this->assertSame(5, Layout::query()->count());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function reset(array $body): TestResponse
    {
        return $this->actingAs($this->administrator)->postJson($this->url(), $body);
    }

    private function url(): string
    {
        return '/canvases/'.$this->canvas->id.'/layout/reset';
    }
}
