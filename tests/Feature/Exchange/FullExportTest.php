<?php

namespace Tests\Feature\Exchange;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

class FullExportTest extends CanvasExchangeTestCase
{
    public function test_the_full_export_holds_every_organisation_canvas_and_template_but_no_users(): void
    {
        $this->richCanvas();
        $archived = Organisation::factory()->create(['name' => 'Oude Org', 'archived_at' => now()]);
        Canvas::factory()->create(['organisation_id' => $archived->id, 'name' => 'Oud canvas', 'archived_at' => now()]);
        Canvas::create(['organisation_id' => null, 'kind' => CanvasKind::Template, 'name' => 'Sjabloon A']);
        $this->actingAs(User::factory()->administrator()->create());

        $response = $this->get('/admin/export/full')->assertOk();
        $data = json_decode((string) $response->getContent(), true);

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('omc-studio-full', $data['format']);
        $this->assertSame(1, $data['format_version']);
        $this->assertSame(['Acme Zorg', 'Oude Org'], array_column(array_column($data['organisations'], 'organisation'), 'name'));
        $this->assertNotNull($data['organisations'][1]['archived_at']);
        $this->assertSame('Oud canvas', $data['organisations'][1]['canvases'][0]['name']);
        $this->assertNotNull($data['organisations'][1]['canvases'][0]['archived_at']);
        $this->assertCount(2, $data['organisations'][0]['cost_records']);
        $this->assertSame(['Sjabloon A'], array_column($data['templates'], 'name'));
        $this->assertStringNotContainsString('password', (string) $response->getContent());
        $this->assertArrayNotHasKey('users', $data);
    }

    public function test_the_export_is_logged_without_file_contents(): void
    {
        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('full_exported', \Mockery::on(fn (array $context) => array_keys($context) === ['user_id']));
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/export/full')->assertOk();
    }

    public function test_the_exchange_page_renders_for_an_administrator(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/exchange')->assertInertia(fn (Assert $page) => $page->component('Admin/Exchange'));
    }

    public function test_a_reader_may_not_export(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/export/full')->assertForbidden();
        $this->get('/admin/exchange')->assertForbidden();
    }
}
