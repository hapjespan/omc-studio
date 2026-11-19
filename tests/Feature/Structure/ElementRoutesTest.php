<?php

namespace Tests\Feature\Structure;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\User;

class ElementRoutesTest extends StructureTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    private function acting(): static
    {
        return $this->actingAs($this->administrator);
    }

    public function test_a_domain_is_created(): void
    {
        $this->acting()->post("/canvases/{$this->canvas->id}/elements", ['kind' => 'domain', 'area' => 'primary_activities', 'name' => 'Inkoop'])->assertRedirect();

        $this->assertSame('Inkoop', Element::firstOrFail()->name);
    }

    public function test_an_activity_is_created_under_a_domain(): void
    {
        $domain = $this->domain();

        $this->acting()->post("/canvases/{$this->canvas->id}/elements", ['kind' => 'activity', 'area' => 'primary_activities', 'parent_id' => $domain->id, 'name' => 'Werving'])->assertRedirect();

        $this->assertSame($domain->id, Element::where('kind', 'activity')->firstOrFail()->parent_id);
    }

    public function test_a_structure_refusal_comes_back_on_the_key_rule(): void
    {
        $this->acting()->post("/canvases/{$this->canvas->id}/elements", ['kind' => 'activity', 'area' => 'primary_activities', 'name' => 'Los'])
            ->assertSessionHasErrors(['rule' => 'Een activiteit hoort onder een domein of een cluster']);

        $this->assertSame(0, Element::count());
    }

    public function test_an_invalid_kind_is_refused(): void
    {
        $this->acting()->post("/canvases/{$this->canvas->id}/elements", ['kind' => 'group', 'area' => 'primary_activities', 'name' => 'X'])->assertSessionHasErrors('kind');
    }

    public function test_fields_are_updated(): void
    {
        $domain = $this->domain();

        $this->acting()->patch("/canvases/{$this->canvas->id}/elements/{$domain->id}", ['name' => 'Verkoop', 'description' => 'Tekst'])->assertRedirect();

        $this->assertSame('Verkoop', $domain->fresh()->name);
    }

    public function test_hangt_onder_moves_an_activity(): void
    {
        $first = $this->domain('primary_activities', 'Een');
        $second = $this->domain('supporting_activities', 'Twee');
        $activity = $this->activity($first);

        $this->acting()->patch("/canvases/{$this->canvas->id}/elements/{$activity->id}", ['parent_id' => $second->id])->assertRedirect();

        $this->assertSame($second->id, $activity->fresh()->parent_id);
    }

    public function test_a_domain_moves_to_another_area_by_key(): void
    {
        $domain = $this->domain('primary_activities');

        $this->acting()->patch("/canvases/{$this->canvas->id}/elements/{$domain->id}", ['area' => 'steering'])->assertRedirect();

        $this->assertSame($this->area('steering')->id, $domain->fresh()->area_id);
    }

    public function test_a_refused_move_leaves_the_field_changes_undone(): void
    {
        $domain = $this->domain('primary_activities', 'Oud');
        $other = $this->domain('actors', 'Groep');

        $this->acting()->patch("/canvases/{$this->canvas->id}/elements/{$domain->id}", ['name' => 'Nieuw', 'area' => 'actors'])
            ->assertSessionHasErrors(['rule' => 'Verplaatsen kan alleen naar een area van dezelfde soort']);

        $this->assertSame('Oud', $domain->fresh()->name);
        $this->assertNotNull($other->fresh());
    }

    public function test_reorder_archive_and_restore(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'Een');
        $second = $this->activity($domain, 'Twee');
        $base = "/canvases/{$this->canvas->id}/elements";

        $this->acting()->post("$base/{$second->id}/reorder", ['direction' => 'up'])->assertRedirect();
        $this->assertSame(0, $second->fresh()->position);

        $this->acting()->post("$base/{$first->id}/archive")->assertRedirect();
        $this->assertNotNull($first->fresh()->archived_at);

        $this->acting()->post("$base/{$first->id}/restore")->assertRedirect();
        $this->assertNull($first->fresh()->archived_at);
    }

    public function test_an_invalid_direction_is_refused(): void
    {
        $domain = $this->domain();

        $this->acting()->post("/canvases/{$this->canvas->id}/elements/{$domain->id}/reorder", ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_delete_flashes_an_undo_token_that_restores(): void
    {
        $domain = $this->domain();

        $response = $this->acting()->delete("/canvases/{$this->canvas->id}/elements/{$domain->id}")->assertRedirect();

        $this->assertSame(0, Element::count());
        $token = session('undo_token');
        $this->assertNotNull($token);
        $this->acting()->post("/undo/$token")->assertRedirect();
        $this->assertSame(1, Element::count());
    }

    public function test_deleting_a_cluster_takes_the_choice(): void
    {
        $domain = $this->domain();
        $cluster = $this->cluster($domain, 'Groep');
        $activity = $this->activity($cluster);

        $this->acting()->delete("/canvases/{$this->canvas->id}/elements/{$cluster->id}")->assertSessionHasErrors('rule');
        $this->acting()->delete("/canvases/{$this->canvas->id}/elements/{$cluster->id}", ['cluster_children' => 'lift'])->assertRedirect();

        $this->assertSame($domain->id, $activity->fresh()->parent_id);
    }

    public function test_an_element_of_another_canvas_is_not_found(): void
    {
        $other = Canvas::factory()->create();
        $foreign = $this->domain('primary_activities', 'Vreemd', $other);

        $this->acting()->patch("/canvases/{$this->canvas->id}/elements/{$foreign->id}", ['name' => 'Kaping'])->assertNotFound();
        $this->acting()->delete("/canvases/{$this->canvas->id}/elements/{$foreign->id}")->assertNotFound();

        $this->assertSame('Vreemd', $foreign->fresh()->name);
    }

    public function test_a_reader_gets_403_on_every_route_and_a_guest_is_sent_away(): void
    {
        $domain = $this->domain();
        $reader = User::factory()->create(['role' => 'reader']);
        $base = "/canvases/{$this->canvas->id}/elements";

        foreach ([
            ['post', $base], ['patch', "$base/{$domain->id}"], ['post', "$base/{$domain->id}/reorder"],
            ['post', "$base/{$domain->id}/archive"], ['post', "$base/{$domain->id}/restore"], ['delete', "$base/{$domain->id}"],
        ] as [$method, $uri]) {
            $this->actingAs($reader)->{$method}($uri)->assertForbidden();
        }

        $this->assertSame(1, Element::count());
    }
}
