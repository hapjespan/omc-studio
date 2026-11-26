<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteListTest extends TestCase
{
    private const FILE = 'tests/fixtures/routes.json';

    /** @return list<string> */
    private function uris(): array
    {
        $uris = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = '/'.ltrim($route->uri(), '/');

            if (! str_starts_with($uri, '/_') && ! in_array($uri, ['/up', '/storage/{path}'], true) && ! str_starts_with($uri, '/storage/')) {
                $uris[$uri] = $uri;
            }
        }

        $uris = array_values($uris);
        sort($uris);

        return $uris;
    }

    public function test_the_route_fixture_for_the_front_end_equals_the_registered_routes(): void
    {
        if (getenv('UPDATE_ROUTES') === '1') {
            file_put_contents(base_path(self::FILE), json_encode($this->uris(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }

        $this->assertFileExists(base_path(self::FILE));
        $this->assertSame($this->uris(), json_decode((string) file_get_contents(base_path(self::FILE)), true), 'Run UPDATE_ROUTES=1 php artisan test --filter=RouteListTest to refresh tests/fixtures/routes.json');
    }
}
