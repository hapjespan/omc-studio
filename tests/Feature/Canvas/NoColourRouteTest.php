<?php

namespace Tests\Feature\Canvas;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NoColourRouteTest extends TestCase
{
    public function test_no_route_exists_to_change_a_default_colour(): void
    {
        $offending = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => preg_match('/colou?r|palette|theme|style/i', $route->uri().' '.($route->getName() ?? '')))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $offending);
    }

    public function test_the_element_write_routes_accept_no_colour_field(): void
    {
        $writes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'canvases/{canvas}/elements') && array_intersect($route->methods(), ['POST', 'PATCH', 'PUT']));

        $this->assertNotEmpty($writes);
        foreach (glob(base_path('app/Http/Requests/Element*')) ?: [] as $request) {
            $this->assertDoesNotMatchRegularExpression('/[\'"](fill|colou?r|text_colou?r)[\'"]/i', (string) file_get_contents($request));
        }
    }
}
