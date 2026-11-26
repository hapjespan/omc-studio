<?php

namespace Tests\Feature\Canvas;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Operations\Steps\BlankTemplateStep;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        (new BlankTemplateStep)();
    }

    public function test_each_active_organisation_is_a_section_with_its_active_canvases_in_name_order(): void
    {
        $first = Organisation::factory()->create(['name' => 'Noord']);
        $second = Organisation::factory()->create(['name' => 'Zuid']);
        Organisation::factory()->create(['name' => 'Oost', 'archived_at' => now()]);
        Canvas::factory()->create(['organisation_id' => $first->id, 'name' => 'B model']);
        Canvas::factory()->create(['organisation_id' => $first->id, 'name' => 'A model']);
        Canvas::factory()->create(['organisation_id' => $first->id, 'name' => 'Oud', 'archived_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page
            ->component('Overview')
            ->where('organisations.0.name', 'Noord')
            ->where('organisations.0.canvases.0.name', 'A model')
            ->where('organisations.0.canvases.1.name', 'B model')
            ->has('organisations.0.canvases', 2)
            ->where('organisations.1.name', 'Zuid')
            ->has('organisations.1.canvases', 0)
            ->has('organisations', 2));
    }

    public function test_a_sample_organisation_is_flagged(): void
    {
        Organisation::factory()->create(['name' => 'Noord']);
        Organisation::factory()->create(['name' => 'Zuid', 'is_sample' => true]);

        $this->actingAs(User::factory()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page
            ->where('organisations.0.is_sample', false)
            ->where('organisations.1.is_sample', true));
    }

    public function test_archived_canvases_appear_with_the_toggle(): void
    {
        $organisation = Organisation::factory()->create();
        Canvas::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Oud', 'archived_at' => now()]);

        $this->actingAs(User::factory()->administrator()->create())->get('/overview?archived=1')->assertInertia(fn (Assert $page) => $page
            ->has('organisations.0.canvases', 1)
            ->where('organisations.0.canvases.0.archived', true)
            ->where('showArchived', true));
    }

    public function test_a_card_carries_the_elements_for_its_miniature_without_archived_ones(): void
    {
        $canvas = Canvas::factory()->create();
        Element::factory()->create(['canvas_id' => $canvas->id, 'name' => 'Zorg']);
        Element::factory()->create(['canvas_id' => $canvas->id, 'name' => 'Oud', 'archived_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page
            ->has('organisations.0.canvases.0.elements', 1)
            ->where('organisations.0.canvases.0.elements.0.name', 'Zorg')
            ->where('organisations.0.canvases.0.elements.0.area', 'primary_activities')
            ->has('layout.areas', 8)
            ->where('layout.areas.0.label', 'Koers'));
    }

    public function test_an_administrator_gets_the_templates_and_a_reader_does_not(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', true)->where('templates.0.name', 'Leeg canvas'));

        $canvas = Canvas::factory()->create();
        Element::factory()->create(['canvas_id' => $canvas->id]);
        $this->actingAs(User::factory()->administrator()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page
            ->where('organisations.0.canvases.0.counts', ['elements' => 1, 'statements' => 0, 'allocation_sets' => 0, 'finalised_years' => 0, 'snapshots' => 0]));

        $this->actingAs(User::factory()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page
            ->where('canWrite', false)->where('templates', [])->where('organisations.0.canvases.0.counts', null));
    }

    public function test_template_cards_skip_the_blank_template_and_carry_their_elements_for_an_administrator_only(): void
    {
        $template = Canvas::factory()->template()->create(['name' => 'Basis']);
        Element::factory()->create(['canvas_id' => $template->id, 'name' => 'Zorg']);

        $this->actingAs(User::factory()->administrator()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page
            ->has('templateCards', 1)
            ->where('templateCards.0.name', 'Basis')
            ->where('templateCards.0.elements.0.name', 'Zorg')
            ->where('templateCards.0.counts', ['elements' => 1, 'statements' => 0, 'allocation_sets' => 0, 'finalised_years' => 0, 'snapshots' => 0]));

        $this->actingAs(User::factory()->create())->get('/overview')->assertInertia(fn (Assert $page) => $page->where('templateCards', []));
    }

    public function test_the_root_shows_the_overview_and_a_guest_is_sent_to_sign_in(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertInertia(fn (Assert $page) => $page->component('Overview'));
        auth()->logout();
        $this->get('/overview')->assertRedirect('/sign-in');
    }
}
