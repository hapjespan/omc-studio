<?php

namespace Tests\Feature\Layout;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Models\Organisation;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\TemplateService;
use App\Services\Canvas\UnknownAllocationDomain;
use Tests\Feature\Structure\StructureTestCase;

class CopyLayoutTest extends StructureTestCase
{
    private Element $domain;

    private Element $cluster;

    private Element $activity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domain = $this->domain('primary_activities', 'Inkoop');
        $this->cluster = $this->cluster($this->domain);
        $this->activity = $this->activity($this->cluster);

        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->domain->id, 'x' => 10, 'y' => 20, 'width' => 300, 'height' => 120, 'z' => 2, 'style' => ['text_colour' => '#112233'], 'format_version' => 1]);
        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->cluster->id, 'x' => 30, 'y' => 40, 'width' => 160, 'height' => 60, 'z' => 1, 'style' => [], 'format_version' => 1]);
        Layout::create(['canvas_id' => $this->canvas->id, 'area_id' => $this->domain->area_id, 'x' => 0, 'y' => 180, 'width' => 900, 'height' => 400, 'z' => 0, 'style' => [], 'format_version' => 1]);

        $this->canvas->refresh();
    }

    private function copy(?Organisation $target = null): Canvas
    {
        return app(CanvasLifecycle::class)->copy($this->canvas, $target ?? $this->canvas->organisation, 'Kopie');
    }

    /** @return array<string, Element> */
    private function elements(Canvas $canvas): array
    {
        return Element::query()->where('canvas_id', $canvas->getKey())->get()->keyBy('name')->all();
    }

    public function test_the_copy_holds_the_layout_of_every_copied_element_on_its_new_identifier(): void
    {
        $copy = $this->copy();
        $elements = $this->elements($copy);

        $domain = Layout::query()->where('element_id', $elements['Inkoop']->id)->firstOrFail();

        $this->assertSame([10, 20, 300, 120, 2, 1], [$domain->x, $domain->y, $domain->width, $domain->height, $domain->z, $domain->format_version]);
        $this->assertSame(['text_colour' => '#112233'], $domain->style);
        $this->assertSame($copy->getKey(), $domain->canvas_id);
        $this->assertNull($domain->area_id);

        $cluster = Layout::query()->where('element_id', $elements['Werving']->id)->firstOrFail();
        $this->assertSame([30, 40, 160, 60, 1], [$cluster->x, $cluster->y, $cluster->width, $cluster->height, $cluster->z]);
    }

    public function test_an_element_without_a_row_stays_automatic_and_the_source_keeps_its_own_rows(): void
    {
        $copy = $this->copy();

        $this->assertFalse(Layout::query()->where('element_id', $this->elements($copy)['Offreren']->id)->exists());
        $this->assertSame(3, Layout::query()->where('canvas_id', $this->canvas->id)->count());
        $this->assertSame(3, Layout::query()->where('canvas_id', $copy->id)->count());
        $this->assertTrue(Layout::query()->where('element_id', $this->activity->id)->doesntExist());
    }

    public function test_the_area_row_is_carried_on_the_same_area(): void
    {
        $copy = $this->copy();

        $area = Layout::query()->where('canvas_id', $copy->id)->whereNotNull('area_id')->firstOrFail();

        $this->assertSame($this->domain->area_id, $area->area_id);
        $this->assertNull($area->element_id);
        $this->assertSame([0, 180, 900, 400], [$area->x, $area->y, $area->width, $area->height]);
    }

    public function test_a_copy_to_another_organisation_carries_the_same_layout(): void
    {
        $copy = $this->copy(Organisation::factory()->create());

        $this->assertSame(3, Layout::query()->where('canvas_id', $copy->id)->count());
        $this->assertSame(300, Layout::query()->where('element_id', $this->elements($copy)['Inkoop']->id)->value('width'));
    }

    public function test_the_copy_starts_at_the_default_versions(): void
    {
        $copy = $this->copy();

        $this->assertSame(0, (int) $copy->refresh()->layout_version);
        $this->assertNull($copy->layout_saved_at);
    }

    public function test_a_template_and_a_canvas_started_from_it_stay_automatic(): void
    {
        $template = app(TemplateService::class)->saveAsTemplate($this->canvas, 'Sjabloon');
        $started = app(CanvasLifecycle::class)->createFromTemplate($this->canvas->organisation, $template, 'Nieuw');

        $this->assertSame(0, Layout::query()->where('canvas_id', $template->id)->count());
        $this->assertSame(0, Layout::query()->where('canvas_id', $started->id)->count());
        $this->assertSame(0, (int) $started->layout_version);
    }

    public function test_the_layout_of_the_unknown_allocation_domain_follows_it_to_the_copy(): void
    {
        $unknown = UnknownAllocationDomain::ensure($this->canvas);
        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $unknown->id, 'x' => 5, 'y' => 6, 'width' => 200, 'height' => 80, 'z' => 0, 'style' => [], 'format_version' => 1]);

        $copy = $this->copy();
        $copied = Element::query()->where('canvas_id', $copy->getKey())->where('is_unknown_allocation', true)->firstOrFail();

        $row = Layout::query()->where('element_id', $copied->id)->firstOrFail();
        $this->assertSame([5, 6, 200, 80], [$row->x, $row->y, $row->width, $row->height]);
        $this->assertNotSame($unknown->id, $copied->id);
    }
}
