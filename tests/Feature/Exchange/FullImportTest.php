<?php

namespace Tests\Feature\Exchange;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Snapshot\Snapshots;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

class FullImportTest extends CanvasExchangeTestCase
{
    /** @return array<string, mixed> */
    private function exportedFull(): array
    {
        $this->richCanvas();
        Canvas::create(['organisation_id' => null, 'kind' => CanvasKind::Template, 'name' => 'Sjabloon A']);
        $this->actingAs(User::factory()->administrator()->create());

        return json_decode((string) $this->get('/admin/export/full')->assertOk()->getContent(), true);
    }

    /** @return array<string, mixed> */
    private function exportedFullWithSnapshot(): array
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());
        app(Snapshots::class)->take($this->canvas, $this->fiscalYear(2026), 'Voor de stuurgroep');

        return json_decode((string) $this->get('/admin/export/full')->assertOk()->getContent(), true);
    }

    /** @return array<string, mixed> */
    private function emptyFull(): array
    {
        $this->actingAs(User::factory()->administrator()->create());
        $this->wipe();

        return ['format' => 'omc-studio-full', 'format_version' => 1, 'organisations' => [], 'templates' => []];
    }

    /** @return array<string, mixed> */
    private function template(string $name): array
    {
        return ['name' => $name, 'statements' => [], 'elements' => []];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<JsonResponse>
     */
    private function send(string $url, array $data): TestResponse
    {
        return $this->post($url, ['file' => UploadedFile::fake()->createWithContent('full.json', json_encode($data))], ['Accept' => 'application/json']);
    }

    public function test_it_is_refused_while_an_organisation_exists(): void
    {
        $data = $this->exportedFull();

        $this->send('/admin/import/full/preview', $data)->assertUnprocessable()->assertJsonValidationErrors(['rule' => 'Volledige import kan alleen in een lege applicatie. Verwijder eerst alle organisaties']);
        $this->send('/admin/import/full', $data)->assertUnprocessable()->assertJsonValidationErrors(['rule']);
    }

    public function test_the_dry_run_reports_totals_and_writes_nothing(): void
    {
        $data = $this->exportedFull();
        $this->wipe();

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(0, $report['count']);
        $this->assertSame(['organisations' => 1, 'canvases' => 1, 'cost_records' => 2, 'templates' => 1], $report['totals']);
        $this->assertSame(0, Organisation::count());
    }

    public function test_a_faulty_file_names_its_real_place_once_for_every_canvas(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][0]['canvases'][] = [...$data['organisations'][0]['canvases'][0], 'name' => 'Tweede canvas'];
        $data['organisations'][0]['cost_records'][0]['yearly_figures'][0]['budget_cents'] = -1;

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(1, $report['count']);
        $this->assertStringStartsWith('organisations[0].cost_records[0].yearly_figures[0].budget_cents', $report['errors'][0]['location']);
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(0, Organisation::count());
    }

    public function test_a_faulty_canvas_names_the_canvas_without_an_inner_prefix(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][0]['canvases'][] = [...$data['organisations'][0]['canvases'][0], 'name' => 'Tweede canvas'];
        $data['organisations'][0]['canvases'][1]['elements'][0]['name'] = '';

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(1, $report['count']);
        $this->assertSame('organisations[0].canvases[1].elements[0].name', $report['errors'][0]['location']);
    }

    public function test_a_file_of_another_format_is_refused(): void
    {
        $this->emptyFull();

        $this->send('/admin/import/full/preview', ['format' => 'omc-studio-canvas'])->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    public function test_a_template_name_already_present_gets_the_suffix_and_archived_state_is_kept(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][0]['archived_at'] = now()->toIso8601String();
        $data['organisations'][0]['canvases'][0]['archived_at'] = now()->toIso8601String();
        Canvas::create(['organisation_id' => null, 'kind' => CanvasKind::Template, 'name' => 'Sjabloon A']);

        $this->send('/admin/import/full', $data)->assertOk();

        $this->assertTrue(Canvas::where('scope_key', 'template')->where('name', 'Sjabloon A (geïmporteerd)')->exists());
        $this->assertNotNull(Organisation::firstOrFail()->archived_at);
        $this->assertNotNull(Canvas::where('name', 'Basis canvas')->firstOrFail()->archived_at);
    }

    public function test_a_template_name_taken_twice_over_is_reported_in_the_dry_run(): void
    {
        $data = $this->emptyFull();
        $data['templates'] = [$this->template('Sjabloon A')];

        foreach (['Sjabloon A', 'Sjabloon A (geïmporteerd)'] as $name) {
            Canvas::create(['organisation_id' => null, 'kind' => CanvasKind::Template, 'name' => $name]);
        }

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(1, $report['count']);
        $this->assertSame('templates[0].name', $report['errors'][0]['location']);
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(2, Canvas::where('scope_key', 'template')->where('is_blank_template', false)->count());
    }

    public function test_the_same_template_name_twice_in_the_file_gets_the_suffix(): void
    {
        $data = $this->emptyFull();
        $data['templates'] = [$this->template('Sjabloon A'), $this->template('Sjabloon A')];

        $this->send('/admin/import/full/preview', $data)->assertOk()->assertJsonPath('count', 0);
        $this->send('/admin/import/full', $data)->assertOk();

        $this->assertSame(['Sjabloon A', 'Sjabloon A (geïmporteerd)'], Canvas::where('scope_key', 'template')->where('is_blank_template', false)->orderBy('id')->pluck('name')->all());
    }

    public function test_a_suffixed_template_name_stays_within_150_characters(): void
    {
        $data = $this->emptyFull();
        $data['templates'] = [$this->template(str_repeat('a', 150)), $this->template(str_repeat('a', 150))];

        $this->send('/admin/import/full', $data)->assertOk();

        $names = Canvas::where('scope_key', 'template')->where('is_blank_template', false)->orderBy('id')->pluck('name')->all();

        $this->assertSame(150, mb_strlen((string) $names[1]));
        $this->assertStringEndsWith(' (geïmporteerd)', (string) $names[1]);
    }

    public function test_an_archived_at_that_is_no_date_is_reported_in_the_dry_run(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][0]['archived_at'] = 'gisteren';
        $data['organisations'][0]['canvases'][0]['archived_at'] = ['soms'];

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(2, $report['count']);
        $this->assertSame(['organisations[0].archived_at', 'organisations[0].canvases[0].archived_at'], array_column($report['errors'], 'location'));
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(0, Organisation::count());
    }

    public function test_a_cost_record_id_that_is_no_uuid_is_reported_in_the_dry_run(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $old = $data['organisations'][0]['cost_records'][0]['id'];
        $data['organisations'][0]['cost_records'][0]['id'] = 'LC-001';
        $data['organisations'][0]['canvases'][0]['allocation_sets'] = array_map(
            fn (array $set) => $set['cost_record_id'] === $old ? [...$set, 'cost_record_id' => 'LC-001'] : $set,
            $data['organisations'][0]['canvases'][0]['allocation_sets'],
        );

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(1, $report['count']);
        $this->assertSame('organisations[0].cost_records[0].id', $report['errors'][0]['location']);
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(0, Organisation::count());
    }

    public function test_the_same_cost_record_id_in_two_organisations_is_reported_in_the_dry_run(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][1] = [...$data['organisations'][0], 'canvases' => []];
        $data['organisations'][1]['organisation']['name'] = 'Tweede Org';

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(2, $report['count']);
        $this->assertSame(['organisations[1].cost_records[0].id', 'organisations[1].cost_records[1].id'], array_column($report['errors'], 'location'));
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(0, Organisation::count());
    }

    public function test_a_name_that_is_no_text_is_reported_and_not_an_error_page(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][0]['organisation']['name'] = ['Acme'];
        $data['organisations'][0]['canvases'][0]['name'] = ['Basis'];
        $data['templates'][0]['name'] = ['Sjabloon'];

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(['organisations[0].organisation.name', 'organisations[0].canvases[0].name', 'templates[0].name'], array_column($report['errors'], 'location'));
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(0, Organisation::count());
    }

    public function test_a_snapshot_is_imported_with_its_checksum_and_marked_as_imported(): void
    {
        $data = $this->exportedFullWithSnapshot();
        $this->wipe();

        $this->send('/admin/import/full', $data)->assertOk();

        $snapshot = Snapshot::firstOrFail();

        $this->assertSame('Voor de stuurgroep', $snapshot->name);
        $this->assertSame(2026, $snapshot->fiscal_year);
        $this->assertTrue($snapshot->imported);
        $this->assertNull($snapshot->created_by);
        $this->assertNotNull(app(Snapshots::class)->read($snapshot));
        $this->assertSame($data['organisations'][0]['canvases'][0]['snapshots'][0]['checksum'], $snapshot->checksum);
    }

    public function test_a_snapshot_whose_checksum_does_not_match_refuses_the_file_by_name(): void
    {
        $data = $this->exportedFullWithSnapshot();
        $this->wipe();
        $data['organisations'][0]['canvases'][0]['snapshots'][0]['content']['canvas_name'] = 'Vervalst';

        $this->send('/admin/import/full/preview', $data)->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Voor de stuurgroep']);
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(0, Organisation::count());
        $this->assertSame(0, Snapshot::count());
    }

    public function test_an_organisation_without_canvases_keeps_its_cost_records(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][0]['canvases'] = [];

        $this->send('/admin/import/full', $data)->assertOk();

        $this->assertSame(2, Organisation::firstOrFail()->costRecords()->count());
        $this->assertSame(0, Canvas::where('kind', CanvasKind::Canvas)->count());
    }
}
