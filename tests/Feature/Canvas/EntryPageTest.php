<?php

namespace Tests\Feature\Canvas;

use App\Enums\ElementKind;
use App\Enums\StatementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Statement;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EntryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
    }

    public function test_the_left_panel_lists_the_eight_areas_with_count_lines_and_the_first_activity_area_is_chosen(): void
    {
        $canvas = Canvas::factory()->create();
        $steering = Area::where('key', 'steering')->firstOrFail();
        $domain = Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => $steering->id, 'kind' => ElementKind::Domain]);
        Element::factory()->count(2)->create(['canvas_id' => $canvas->id, 'area_id' => $steering->id, 'kind' => ElementKind::Activity, 'parent_id' => $domain->id]);
        Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => $steering->id, 'kind' => ElementKind::Activity, 'parent_id' => $domain->id, 'archived_at' => now()]);
        Element::factory()->count(3)->create(['canvas_id' => $canvas->id, 'area_id' => Area::where('key', 'actors')->firstOrFail()->id, 'kind' => ElementKind::Domain]);
        Statement::create(['canvas_id' => $canvas->id, 'kind' => StatementKind::Mission, 'title' => 'Missie', 'body' => 'x', 'position' => 1]);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/entry")->assertInertia(fn (Assert $page) => $page
            ->component('Canvas/Entry')
            ->has('areas', 8)
            ->where('areas.0.key', 'direction')
            ->where('areas.0.summary', '1 tekst')
            ->where('areas.1.summary', '1 domein · 2 activiteiten')
            ->where('areas.3.summary', '3 groepen')
            ->where('areas.2.summary', '0 domeinen · 0 activiteiten')
            ->where('selected', 'steering'));
    }

    public function test_the_page_carries_every_element_of_the_canvas_with_its_area_key_and_the_four_modalities(): void
    {
        $canvas = Canvas::factory()->create();
        $domain = Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => Area::where('key', 'steering')->firstOrFail()->id, 'kind' => ElementKind::Domain, 'name' => 'Inkoop']);
        Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => $domain->area_id, 'kind' => ElementKind::Activity, 'parent_id' => $domain->id, 'name' => 'Werving', 'archived_at' => now()]);
        Element::factory()->create(['area_id' => $domain->area_id, 'kind' => ElementKind::Domain, 'name' => 'Andere']);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/entry")->assertInertia(function (Assert $page) use ($domain) {
            $elements = collect($page->toArray()['props']['elements'])->keyBy('name');

            $this->assertCount(2, $elements);
            $this->assertSame(['id' => $domain->id, 'area' => 'steering', 'parent' => null, 'kind' => 'domain', 'archived' => false], collect($elements['Inkoop'])->only(['id', 'area', 'parent', 'kind', 'archived'])->all());
            $this->assertSame($domain->id, $elements['Werving']['parent']);
            $this->assertTrue($elements['Werving']['archived']);
            $page->has('modalities', 4)->where('modalities.0.letter', 'C');
        });
    }

    public function test_a_reader_and_an_archived_canvas_are_redirected_to_view(): void
    {
        $canvas = Canvas::factory()->create();
        $archived = Canvas::factory()->create(['archived_at' => now()]);

        $this->actingAs(User::factory()->create())->get("/canvases/{$canvas->id}/entry")->assertRedirect("/canvases/{$canvas->id}/view");
        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$archived->id}/entry")->assertRedirect("/canvases/{$archived->id}/view");
    }

    public function test_a_template_opens_in_entry_for_an_administrator_and_a_guest_is_sent_to_sign_in(): void
    {
        $template = Canvas::factory()->template()->create();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$template->id}/entry")->assertInertia(fn (Assert $page) => $page->component('Canvas/Entry'));
        auth()->logout();
        $this->get("/canvases/{$template->id}/entry")->assertRedirect('/sign-in');
    }
}
