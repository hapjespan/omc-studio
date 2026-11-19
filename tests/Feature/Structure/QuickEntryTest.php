<?php

namespace Tests\Feature\Structure;

use App\Models\Element;
use App\Models\User;
use App\Services\Structure\ElementTree;
use Illuminate\Validation\ValidationException;

class QuickEntryTest extends StructureTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    private function url(string $suffix = ''): string
    {
        return "/canvases/{$this->canvas->id}/quick-entry{$suffix}";
    }

    public function test_the_preview_returns_marked_lines_and_writes_nothing(): void
    {
        $this->domain();

        $this->actingAs($this->administrator)->postJson($this->url('/preview'), ['area' => 'primary_activities', 'text' => "Inkoop\n  Offreren\nVerkoop"])
            ->assertOk()
            ->assertJsonPath('errors', [])
            ->assertJsonPath('lines.0.status', 'exists')
            ->assertJsonPath('lines.1.status', 'new')
            ->assertJsonPath('lines.1.kind', 'activity')
            ->assertJsonPath('lines.2.status', 'new');

        $this->assertSame(1, Element::count());
    }

    public function test_the_preview_reports_line_errors(): void
    {
        $this->actingAs($this->administrator)->postJson($this->url('/preview'), ['area' => 'primary_activities', 'text' => '  Inkoop'])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Regel 1: inspringing past niet bij de regel erboven');
    }

    public function test_saving_creates_the_tree_and_reuses_existing_names(): void
    {
        $inkoop = $this->domain();

        $this->actingAs($this->administrator)->post($this->url(), ['area' => 'primary_activities', 'text' => "Inkoop\n  Werving\n    Offreren\n  Bestellen\nVerkoop\n  Offerte"])->assertRedirect();

        $this->assertSame(6, Element::count());
        $werving = Element::where('name', 'Werving')->firstOrFail();
        $this->assertSame($inkoop->id, $werving->parent_id);
        $this->assertSame($werving->id, Element::where('name', 'Offreren')->firstOrFail()->parent_id);
    }

    public function test_saving_with_line_errors_creates_nothing(): void
    {
        $this->actingAs($this->administrator)->post($this->url(), ['area' => 'primary_activities', 'text' => "Inkoop\n      Diep"])
            ->assertSessionHasErrors(['rule' => 'Regel 2: inspringing past niet bij de regel erboven']);

        $this->assertSame(0, Element::count());
    }

    public function test_a_refusal_at_save_time_creates_nothing(): void
    {
        $real = app(ElementTree::class);
        $calls = 0;

        $this->partialMock(ElementTree::class, fn ($mock) => $mock->shouldReceive('create')->andReturnUsing(function (...$arguments) use ($real, &$calls) {
            if (++$calls === 2) {
                throw ValidationException::withMessages(['rule' => 'refused']);
            }

            return $real->create(...$arguments);
        }));

        $this->actingAs($this->administrator)->post($this->url(), ['area' => 'primary_activities', 'text' => "Inkoop\n  Offreren"])
            ->assertSessionHasErrors(['rule' => 'refused']);

        $this->assertSame(0, Element::count());
    }

    public function test_an_archived_canvas_refuses_the_save(): void
    {
        $this->canvas->update(['archived_at' => now()]);

        $this->actingAs($this->administrator)->post($this->url(), ['area' => 'primary_activities', 'text' => 'Inkoop'])
            ->assertSessionHasErrors(['rule' => 'Dit canvas is gearchiveerd']);

        $this->assertSame(0, Element::count());
    }

    public function test_the_direction_area_takes_no_elements(): void
    {
        $this->actingAs($this->administrator)->post($this->url(), ['area' => 'direction', 'text' => 'Inkoop'])
            ->assertSessionHasErrors(['rule' => 'In Koers staan geen elementen']);
    }

    public function test_a_reader_is_refused_on_both_routes(): void
    {
        $reader = User::factory()->create();

        $this->actingAs($reader)->postJson($this->url('/preview'), ['area' => 'primary_activities', 'text' => 'Inkoop'])->assertForbidden();
        $this->actingAs($reader)->post($this->url(), ['area' => 'primary_activities', 'text' => 'Inkoop'])->assertForbidden();
    }
}
