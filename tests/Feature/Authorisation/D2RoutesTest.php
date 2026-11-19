<?php

namespace Tests\Feature\Authorisation;

use App\Models\Application;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\SystemNotice;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class D2RoutesTest extends TestCase
{
    use RefreshDatabase;

    private const URI_PATTERNS = [
        'admin/owners',
        'admin/applications',
        'admin/organisations/*/owners*',
        'admin/organisations/*/applications*',
        'admin/system-notices*',
        'organisations/*/fiscal-years',
        'fiscal-years/*',
        'organisations/*/cost-records*',
        'cost-records/*',
        'canvases/*/cost-records',
        'canvases/*/allocation',
        'canvases/*/years/*',
        'canvases/*/elements/*/redistribution',
    ];

    private const CATALOGUE = [
        'DELETE admin/organisations/{organisation}/applications/{application}',
        'DELETE admin/organisations/{organisation}/owners/{owner}',
        'DELETE canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation',
        'DELETE cost-records/{costRecord}',
        'DELETE cost-records/{costRecord}/years/{year}',
        'DELETE fiscal-years/{fiscalYear}',
        'GET admin/applications',
        'GET admin/organisations/{organisation}/applications',
        'GET admin/organisations/{organisation}/owners',
        'GET admin/owners',
        'GET admin/system-notices',
        'GET canvases/{canvas}/allocation',
        'GET canvases/{canvas}/cost-records',
        'GET canvases/{canvas}/elements/{element}/redistribution',
        'GET organisations/{organisation}/cost-records',
        'GET organisations/{organisation}/cost-records/import',
        'PATCH admin/organisations/{organisation}/applications/{application}',
        'PATCH admin/organisations/{organisation}/owners/{owner}',
        'PATCH cost-records/{costRecord}',
        'POST admin/organisations/{organisation}/applications',
        'POST admin/organisations/{organisation}/owners',
        'POST admin/system-notices/{notice}/seen',
        'POST canvases/{canvas}/elements/{element}/redistribution',
        'POST canvases/{canvas}/years/{year}/carry-over',
        'POST canvases/{canvas}/years/{year}/finalise',
        'POST canvases/{canvas}/years/{year}/reopen',
        'POST cost-records/{costRecord}/archive',
        'POST cost-records/{costRecord}/restore',
        'POST organisations/{organisation}/cost-records',
        'POST organisations/{organisation}/cost-records/bulk-delete',
        'POST organisations/{organisation}/cost-records/import',
        'POST organisations/{organisation}/cost-records/import/preview',
        'POST organisations/{organisation}/fiscal-years',
        'PUT canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation',
        'PUT cost-records/{costRecord}/years/{year}',
    ];

    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var array<string, string> */
    private array $parameters = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(ReferenceDataStep::class)();

        $organisation = Organisation::factory()->create();
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        $owner = Owner::factory()->create(['organisation_id' => $organisation->id]);

        $this->parameters = [
            '{organisation}' => (string) $organisation->id,
            '{canvas}' => (string) $canvas->id,
            '{owner}' => (string) $owner->id,
            '{application}' => (string) Application::factory()->create(['organisation_id' => $organisation->id])->id,
            '{costRecord}' => (string) CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => $owner->id])->id,
            '{fiscalYear}' => (string) FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026])->id,
            '{element}' => (string) Element::factory()->create(['canvas_id' => $canvas->id, 'area_id' => Area::query()->where('key', 'primary_activities')->firstOrFail()->id])->id,
            '{notice}' => (string) SystemNotice::factory()->create(['canvas_id' => $canvas->id])->id,
            '{year}' => '2026',
        ];
    }

    public function test_the_catalogue_lists_every_d2_route_the_router_knows(): void
    {
        $this->assertSame(self::CATALOGUE, $this->routes());
    }

    public function test_a_new_d2_route_outside_the_catalogue_is_caught(): void
    {
        Route::post('/canvases/{canvas}/years/{year}/rollback', fn () => response('ok'));

        $this->assertContains('POST canvases/{canvas}/years/{year}/rollback', $this->routes());
    }

    public function test_no_d2_route_writes_a_single_allocation_line(): void
    {
        $allocationWrites = array_values(array_filter(
            self::CATALOGUE,
            fn (string $entry) => Str::contains($entry, 'allocation') && ! Str::startsWith($entry, 'GET '),
        ));

        $this->assertSame([
            'DELETE canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation',
            'PUT canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation',
        ], $allocationWrites);

        foreach (self::CATALOGUE as $entry) {
            $this->assertStringNotContainsString('allocation-lines', $entry);
            $this->assertStringNotContainsString('{allocationLine}', $entry);
        }
    }

    public function test_a_reader_is_refused_on_every_d2_route(): void
    {
        $reader = User::factory()->create();

        foreach (self::CATALOGUE as $entry) {
            [$method, $uri] = explode(' ', $entry, 2);

            $this->actingAs($reader)->call($method, $this->path($uri))
                ->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_the_sign_in_page_on_every_d2_route(): void
    {
        foreach (self::CATALOGUE as $entry) {
            [$method, $uri] = explode(' ', $entry, 2);

            $this->call($method, $this->path($uri))->assertRedirect('/sign-in');
        }
    }

    public function test_every_d2_write_route_refuses_a_request_without_an_anti_forgery_token(): void
    {
        $this->enforceRequestForgeryChecks();
        $administrator = User::factory()->administrator()->create();

        foreach (self::CATALOGUE as $entry) {
            [$method, $uri] = explode(' ', $entry, 2);

            if (! in_array($method, self::WRITE_METHODS, true)) {
                continue;
            }

            $this->actingAs($administrator)->call($method, $this->path($uri))
                ->assertStatus(419);
        }
    }

    public function test_an_administrator_reaches_a_d2_read_route_with_the_checks_in_place(): void
    {
        $this->enforceRequestForgeryChecks();

        $this->actingAs(User::factory()->administrator()->create())
            ->get($this->path('organisations/{organisation}/cost-records'))
            ->assertOk();
    }

    private function enforceRequestForgeryChecks(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn (ApplicationContract $app) => new class($app, $app->make('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    private function path(string $uri): string
    {
        return '/'.str_replace(array_keys($this->parameters), array_values($this->parameters), $uri);
    }

    /** @return list<string> */
    private function routes(): array
    {
        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! Str::is(self::URI_PATTERNS, $route->uri())) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $found[] = $method.' '.$route->uri();
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }
}
