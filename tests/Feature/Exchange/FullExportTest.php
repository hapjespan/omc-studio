<?php

namespace Tests\Feature\Exchange;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\SystemNotice;
use App\Models\User;
use App\Services\Exchange\FullExport;
use App\Services\Snapshot\SnapshotContent;
use App\Services\Snapshot\Snapshots;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;

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

    public function test_every_snapshot_rides_along_and_a_damaged_one_is_left_out_and_named(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());
        $snapshots = app(Snapshots::class);
        $sound = $snapshots->take($this->canvas, $this->fiscalYear(2026), 'Voor de stuurgroep');
        $damaged = $snapshots->take($this->canvas, $this->fiscalYear(2027), 'Tweede ronde');

        DB::table('snapshots')->where('id', $damaged->getKey())->update(['content' => '{"schema_version":1}']);

        $data = json_decode((string) $this->get('/admin/export/full')->assertOk()->getContent(), true);
        $exported = $data['organisations'][0]['canvases'][0]['snapshots'];

        $this->assertSame(['Voor de stuurgroep'], array_column($exported, 'name'));
        $this->assertSame($sound->checksum, $exported[0]['checksum']);
        $this->assertSame(2026, $exported[0]['fiscal_year']);
        $this->assertSame(SnapshotContent::SCHEMA_VERSION, $exported[0]['content']['schema_version']);
        $this->assertSame(['Tweede ronde'], SystemNotice::where('kind', 'snapshot_damaged')->get()->map(fn (SystemNotice $notice) => $notice->details['snapshot'])->all());
    }

    public function test_the_export_is_logged_without_file_contents(): void
    {
        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('full_exported', \Mockery::on(fn (array $context) => array_keys($context) === ['user_id']));
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/export/full')->assertOk();
    }

    public function test_an_export_that_fails_to_build_is_not_logged(): void
    {
        $this->mock(FullExport::class, fn (MockInterface $mock) => $mock->shouldReceive('build')->once()->andThrow(new RuntimeException('stuk')));
        $log = Log::spy();
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/export/full')->assertStatus(500);

        $log->shouldNotHaveReceived('channel');
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
