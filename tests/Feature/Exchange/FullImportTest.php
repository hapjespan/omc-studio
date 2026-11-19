<?php

namespace Tests\Feature\Exchange;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    protected function wipe(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        foreach (['allocation_lines', 'allocation_sets', 'yearly_figures', 'finalised_years', 'elements', 'statements', 'cost_records', 'owners', 'applications', 'fiscal_years'] as $table) {
            DB::table($table)->delete();
        }

        DB::table('canvases')->where('is_blank_template', false)->delete();
        DB::table('organisations')->delete();
        DB::statement('PRAGMA foreign_keys = ON');
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

        $this->send('/admin/import/full/preview', $data)->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Volledige import kan alleen in een lege applicatie. Verwijder eerst alle organisaties']);
        $this->send('/admin/import/full', $data)->assertUnprocessable();
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

    public function test_a_faulty_file_is_reported_and_nothing_is_written(): void
    {
        $data = $this->exportedFull();
        $this->wipe();
        $data['organisations'][0]['cost_records'][0]['yearly_figures'][0]['budget_cents'] = -1;

        $report = $this->send('/admin/import/full/preview', $data)->assertOk()->json();

        $this->assertSame(1, $report['count']);
        $this->assertStringStartsWith('organisations[0].canvases[0].cost_records[0].yearly_figures[0].budget_cents', $report['errors'][0]['location']);
        $this->send('/admin/import/full', $data)->assertUnprocessable();
        $this->assertSame(0, Organisation::count());
    }

    public function test_a_file_of_another_format_is_refused(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

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
