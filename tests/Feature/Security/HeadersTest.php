<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class HeadersTest extends TestCase
{
    private const POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function test_the_health_route_and_an_unknown_route_carry_the_policy_too(): void
    {
        $this->get('/up')->assertHeader('Content-Security-Policy', self::POLICY);
        $this->get('/no-such-page')->assertNotFound()->assertHeader('Content-Security-Policy', self::POLICY);
    }

    public function test_a_page_carries_the_content_security_policy_and_the_other_headers(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy', self::POLICY);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_an_authenticated_response_is_not_stored_by_the_browser(): void
    {
        $this->probeRoute();

        $response = $this->actingAs(new User)->get('/security-probe');

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Content-Security-Policy', self::POLICY);
    }

    public function test_an_unauthenticated_response_is_left_to_the_default_cache_control(): void
    {
        $this->probeRoute();

        $response = $this->get('/security-probe');

        $response->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_session_cookie_is_http_only_and_same_site_lax(): void
    {
        $cookie = $this->sessionCookie();

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        $this->assertFalse($cookie->isSecure());
    }

    public function test_the_session_cookie_is_secure_when_the_setting_asks_for_it(): void
    {
        config(['session.secure' => true]);

        $this->assertTrue($this->sessionCookie()->isSecure());
    }

    private function probeRoute(): void
    {
        Route::middleware('web')->get('/security-probe', fn () => response('ok'));
    }

    private function sessionCookie(): Cookie
    {
        config(['session.driver' => 'file']);

        $this->probeRoute();

        $cookie = $this->get('/security-probe')->getCookie((string) config('session.cookie'), false);

        $this->assertNotNull($cookie);

        return $cookie;
    }
}
