<?php

namespace Tests\Feature\Structure;

use App\Models\Canvas;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AreasImmutableTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_creates_renames_reorders_or_deletes_an_area(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('#(^|/)areas?(/|$|\{)#', $route->uri(), $route->uri());
            $this->assertStringNotContainsString('AreaController', $route->getActionName());
        }
    }

    public function test_a_new_canvas_shows_the_eight_areas_in_the_fixed_order(): void
    {
        (new ReferenceDataStep)();
        $canvas = Canvas::factory()->create();

        $keys = ['direction', 'steering', 'governing', 'actors', 'channels', 'value_propositions', 'primary_activities', 'supporting_activities'];

        $this->actingAs(User::factory()->create())->get("/canvases/{$canvas->id}/view")->assertInertia(function (Assert $page) use ($keys) {
            $page->has('layout.areas', 8);

            foreach ($keys as $index => $key) {
                $page->where("layout.areas.{$index}.key", $key)->where("layout.areas.{$index}.position", $index + 1);
            }
        });
    }
}
