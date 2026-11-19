<?php

namespace Tests\Feature\Structure;

use App\Models\Area;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AreasImmutableTest extends TestCase
{
    public function test_no_route_creates_renames_reorders_or_deletes_an_area(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('#(^|/)areas?(/|$|\{)#', $route->uri(), $route->uri());
            $this->assertStringNotContainsString('AreaController', $route->getActionName());
        }
    }

    public function test_the_eight_areas_are_the_reference_set(): void
    {
        $this->assertCount(8, Area::KEYS);
    }
}
