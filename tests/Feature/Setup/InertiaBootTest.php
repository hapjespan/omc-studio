<?php

namespace Tests\Feature\Setup;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InertiaBootTest extends TestCase
{
    public function test_an_inertia_response_renders_in_a_dutch_document(): void
    {
        $this->withoutVite();

        $response = $this->get('/sign-in');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/SignIn'));
        $response->assertSee('<html lang="nl">', false);
    }

    public function test_the_default_locale_is_dutch(): void
    {
        $this->assertSame('nl', config('app.locale'));
    }
}
