<?php

namespace Tests\Feature\Layout;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Structure\StructureTestCase;

class SaveLayoutTest extends StructureTestCase
{
    private User $administrator;

    private Element $domain;

    private Element $activity;

    /** @var array<string, int> */
    private array $loaded;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->domain = $this->domain();
        $this->activity = $this->activity($this->domain);
        $this->canvas->refresh();
        $this->loaded = ['structure_version' => (int) $this->canvas->structure_version, 'layout_version' => (int) $this->canvas->layout_version];
    }

    public function test_a_save_stores_one_row_per_manual_area_and_element_and_returns_the_new_version(): void
    {
        $response = $this->save($this->payload([
            'areas' => ['primary_activities' => $this->box(['x' => 0, 'y' => 180, 'width' => 900, 'height' => 400])],
            'elements' => [(string) $this->domain->id => $this->box(['mode' => 'manual', 'style' => ['fill' => '#F6E6CC']])],
        ]));

        $response->assertOk()->assertJsonPath('layout_version', 1);

        $area = Layout::query()->where('area_id', $this->domain->area_id)->firstOrFail();
        $element = Layout::query()->where('element_id', $this->domain->id)->firstOrFail();

        $this->assertSame([$this->canvas->id, 0, 180, 900, 400, 0, [], 1], [
            $area->canvas_id, $area->x, $area->y, $area->width, $area->height, $area->z, $area->style, $area->format_version,
        ]);
        $this->assertSame(['fill' => '#F6E6CC'], $element->style);
        $this->assertNull(Layout::query()->where('element_id', $this->activity->id)->first());
        $this->assertSame(2, Layout::query()->where('canvas_id', $this->canvas->id)->count());

        $this->canvas->refresh();
        $this->assertSame(1, $this->canvas->layout_version);
        $this->assertNotNull($this->canvas->layout_saved_at);
    }

    public function test_a_save_replaces_the_whole_layout_of_the_canvas(): void
    {
        $this->save($this->payload([
            'elements' => [(string) $this->domain->id => $this->box(['mode' => 'manual'])],
        ]))->assertOk();

        $this->save($this->payload([
            'layout_version' => 1,
            'elements' => [(string) $this->activity->id => $this->box(['mode' => 'manual', 'width' => 100])],
        ]))->assertOk()->assertJsonPath('layout_version', 2);

        $this->assertNull(Layout::query()->where('element_id', $this->domain->id)->first());
        $this->assertSame(1, Layout::query()->where('canvas_id', $this->canvas->id)->count());
    }

    public function test_a_stale_structure_version_is_refused_with_the_structure_code(): void
    {
        $this->domain('primary_activities', 'Verkoop');

        $this->save($this->payload())
            ->assertStatus(409)
            ->assertJsonPath('conflict', 'structure_changed');

        $this->assertSame(0, $this->canvas->fresh()?->layout_version);
    }

    public function test_the_structure_version_is_checked_before_the_layout_version(): void
    {
        $this->domain('primary_activities', 'Verkoop');

        $this->save($this->payload(['layout_version' => 9]))
            ->assertStatus(409)
            ->assertJsonPath('conflict', 'structure_changed');
    }

    public function test_a_stale_layout_version_is_refused_with_the_layout_code_and_stores_nothing(): void
    {
        $this->save($this->payload([
            'elements' => [(string) $this->domain->id => $this->box(['mode' => 'manual'])],
        ]))->assertOk();

        $this->save($this->payload([
            'elements' => [(string) $this->activity->id => $this->box(['mode' => 'manual', 'width' => 100])],
        ]))
            ->assertStatus(409)
            ->assertJsonPath('conflict', 'layout_changed');

        $this->assertNotNull(Layout::query()->where('element_id', $this->domain->id)->first());
        $this->assertSame(1, $this->canvas->fresh()?->layout_version);
    }

    public function test_a_missing_element_is_refused_as_a_rule_and_stores_nothing(): void
    {
        $payload = $this->payload();
        unset($payload['elements'][(string) $this->activity->id]);

        $this->save($payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.rule.0', 'Opmaak Offreren: dit element ontbreekt in de opmaak');

        $this->assertSame(0, Layout::query()->count());
        $this->assertSame(0, $this->canvas->fresh()?->layout_version);
    }

    public function test_an_element_outside_its_parent_is_refused(): void
    {
        $this->save($this->payload([
            'elements' => [
                (string) $this->domain->id => $this->box(['mode' => 'manual', 'width' => 200]),
                (string) $this->activity->id => $this->box(['mode' => 'manual', 'x' => 100, 'width' => 200]),
            ],
        ]))->assertStatus(422)->assertJsonPath('errors.rule.0', 'Opmaak Offreren: dit element valt buiten de breedte van Inkoop');

        $this->assertSame(0, Layout::query()->count());
    }

    public function test_a_save_never_changes_the_structure(): void
    {
        $this->save($this->payload([
            'elements' => [(string) $this->activity->id => $this->box(['mode' => 'manual', 'width' => 100, 'parent' => (string) $this->domain->id])],
        ]))->assertOk();

        $this->canvas->refresh();

        $activity = Element::query()->findOrFail($this->activity->id);

        $this->assertSame($this->domain->id, $activity->parent_id);
        $this->assertSame('Offreren', $activity->name);
        $this->assertSame(2, $this->canvas->structure_version);
    }

    public function test_a_parent_other_than_the_structure_is_refused(): void
    {
        $this->save($this->payload([
            'elements' => [(string) $this->activity->id => $this->box(['mode' => 'manual', 'width' => 100, 'parent' => null])],
        ]))->assertStatus(422)->assertJsonPath('errors.rule.0', 'Opmaak Offreren: dit element hangt in de opmaak onder een ander element');

        $this->assertSame($this->domain->id, $this->activity->fresh()?->parent_id);
    }

    public function test_a_reader_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->putJson($this->url(), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Layout::query()->count());
    }

    public function test_a_template_is_refused(): void
    {
        $template = Canvas::factory()->template()->create();

        $this->actingAs($this->administrator)
            ->putJson('/canvases/'.$template->id.'/layout', ['structure_version' => 0, 'layout_version' => 0, 'format_version' => 1, 'areas' => [], 'elements' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rule');

        $this->assertSame(0, Layout::query()->count());
    }

    public function test_an_archived_canvas_is_refused(): void
    {
        $this->canvas->update(['archived_at' => now()]);

        $this->save($this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.rule.0', 'Dit canvas is gearchiveerd');

        $this->assertSame(0, Layout::query()->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function save(array $payload): TestResponse
    {
        return $this->actingAs($this->administrator)->putJson($this->url(), $payload);
    }

    private function url(): string
    {
        return '/canvases/'.$this->canvas->id.'/layout';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function payload(array $values = []): array
    {
        return array_replace([
            ...$this->loaded,
            'format_version' => 1,
            'areas' => [],
            'elements' => [],
        ], $values, ['elements' => array_replace([
            (string) $this->domain->id => ['mode' => 'automatic'],
            (string) $this->activity->id => ['mode' => 'automatic'],
        ], $values['elements'] ?? [])]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function box(array $values = []): array
    {
        return array_replace(['x' => 0, 'y' => 0, 'width' => 200, 'height' => 100], $values);
    }
}
