<?php

namespace Tests\Feature\Security;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D1RefusalMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_a_user_sent_without_a_name_with_a_bad_email_or_an_unknown_role_is_refused_by_message(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->post('/admin/users', ['name' => '', 'email' => 'x@example.test', 'role' => 'reader'])
            ->assertSessionHasErrors(['name' => __('users.name_required')]);
        $this->post('/admin/users', ['name' => 'Eva', 'email' => 'geen-adres', 'role' => 'reader'])
            ->assertSessionHasErrors(['email' => __('users.email_invalid')]);
        $this->post('/admin/users', ['name' => 'Eva', 'email' => 'eva@example.test', 'role' => 'boss'])
            ->assertSessionHasErrors(['role' => __('users.role_invalid')]);

        $this->assertSame(1, User::count());
    }

    public function test_an_organisation_without_a_name_is_refused_by_message(): void
    {
        $this->admin()->post('/admin/organisations', ['name' => ''])
            ->assertSessionHasErrors(['name' => __('organisations.name_required')]);

        $this->assertSame(0, Organisation::count());
    }

    public function test_a_canvas_renamed_to_nothing_is_refused_by_message(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Eerste']);

        $this->admin()->patch("/canvases/{$canvas->id}", ['name' => ''])
            ->assertSessionHasErrors(['name' => __('canvas_lifecycle.name_required')]);

        $this->assertSame('Eerste', $canvas->fresh()->name);
    }

    public function test_a_template_renamed_to_a_taken_name_is_refused_by_message(): void
    {
        $template = Canvas::factory()->template()->create(['name' => 'Eerste']);
        Canvas::factory()->template()->create(['name' => 'Tweede']);

        $this->admin()->patch("/templates/{$template->id}", ['name' => ' tweede '])
            ->assertSessionHasErrors(['name' => __('templates.name_taken')]);

        $this->assertSame('Eerste', $template->fresh()->name);
    }

    public function test_a_goal_title_over_150_characters_is_refused_by_message(): void
    {
        $canvas = Canvas::factory()->create();

        $this->admin()->post("/canvases/{$canvas->id}/goals", ['title' => str_repeat('a', 151)])
            ->assertSessionHasErrors(['title' => __('direction_entry.title_too_long')]);
    }

    public function test_an_element_without_a_name_or_with_an_unknown_icon_is_refused_by_message(): void
    {
        (new ReferenceDataStep)();
        $canvas = Canvas::factory()->create();
        $area = Area::where('key', 'actors')->firstOrFail();

        $this->admin()->post("/canvases/{$canvas->id}/elements", ['kind' => 'domain', 'area' => 'actors', 'name' => ''])
            ->assertSessionHasErrors(['name' => __('rule.name_required')]);

        $group = Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => $area->id, 'kind' => 'domain', 'name' => 'Klanten']);
        $this->admin()->patch("/canvases/{$canvas->id}/elements/{$group->id}", ['icon' => 'skull'])
            ->assertSessionHasErrors(['rule' => __('rule.icon_unknown')]);

        $this->assertNull($group->fresh()->icon);
    }

    public function test_a_list_query_with_an_unknown_sort_filter_or_array_search_is_refused_by_message(): void
    {
        $this->admin()->get('/admin/users?sort=password')->assertSessionHasErrors(['sort' => __('list.invalid_sort')]);
        $this->admin()->get('/admin/users?filters[nothing]=1')->assertSessionHasErrors(['filters' => __('list.invalid_filters')]);
        $this->admin()->get('/admin/users?search[]=a')->assertSessionHasErrors(['query' => __('list.invalid_query')]);
    }
}
