<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DrawingCspTest extends TestCase
{
    private const NARROW = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    private const RELAXED = "default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function test_the_drawing_page_allows_webassembly_compilation(): void
    {
        $this->probeRoutes();

        $this->get('/canvases/7/drawing')->assertHeader('Content-Security-Policy', self::RELAXED);
    }

    public function test_the_drawing_page_keeps_the_image_sources_of_the_png_export(): void
    {
        $this->probeRoutes();

        $policy = (string) $this->get('/canvases/7/drawing')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self' data: blob:", $policy);
    }

    public function test_the_move_endpoint_under_the_drawing_page_keeps_the_narrow_policy(): void
    {
        $this->probeRoutes();

        $this->get('/canvases/7/drawing/moves')->assertHeader('Content-Security-Policy', self::NARROW);
    }

    public function test_a_write_to_the_drawing_path_keeps_the_narrow_policy(): void
    {
        $this->probeRoutes();

        $this->post('/canvases/7/drawing')->assertHeader('Content-Security-Policy', self::NARROW);
    }

    public function test_a_path_that_only_ends_in_drawing_keeps_the_narrow_policy(): void
    {
        $this->probeRoutes();

        $this->get('/canvases/7/elements/drawing')->assertHeader('Content-Security-Policy', self::NARROW);
    }

    public function test_another_page_keeps_the_narrow_policy(): void
    {
        $this->withoutVite();

        $this->get('/sign-in')->assertHeader('Content-Security-Policy', self::NARROW);
        $this->get('/up')->assertHeader('Content-Security-Policy', self::NARROW);
    }

    private function probeRoutes(): void
    {
        foreach (['canvases/{canvas}/drawing', 'canvases/{canvas}/drawing/moves', 'canvases/{canvas}/elements/drawing'] as $path) {
            Route::get($path, fn () => response('ok'));
        }

        Route::post('canvases/{canvas}/drawing', fn () => response('ok'));
    }
}
