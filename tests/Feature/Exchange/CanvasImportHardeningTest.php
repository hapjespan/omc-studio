<?php

namespace Tests\Feature\Exchange;

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\User;
use App\Models\YearlyFigure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

class CanvasImportHardeningTest extends CanvasExchangeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  array<string, mixed>  $fields
     * @return TestResponse<JsonResponse>
     */
    private function send(string $path, array $file, array $fields = []): TestResponse
    {
        return $this->post($path, ['file' => UploadedFile::fake()->createWithContent('canvas.json', (string) json_encode($file)), 'organisation_name' => 'Nieuwe organisatie', 'canvas_name' => 'Kopie', ...$fields], ['Accept' => 'application/json']);
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  array<string, mixed>  $fields
     */
    private function assertRefusedAt(array $file, string $location, array $fields = []): void
    {
        $locations = array_column($this->send('/imports/canvas/preview', $file, $fields)->assertOk()->json('errors'), 'location');
        $this->assertContains($location, $locations);
        $before = [Organisation::count(), Canvas::count(), Element::count(), CostRecord::count()];

        $this->send('/imports/canvas', $file, $fields)->assertStatus(422);
        $this->assertSame($before, [Organisation::count(), Canvas::count(), Element::count(), CostRecord::count()]);
    }

    /** @return array<string, mixed> */
    private function sameOrganisation(): array
    {
        return ['organisation_id' => $this->canvas->organisation_id, 'organisation_name' => null];
    }

    private function elementIndex(string $name): int
    {
        return (int) array_search($name, array_column($this->exported()['canvas']['elements'], 'name'), true);
    }

    public function test_a_cost_record_without_a_code_key_imports_into_another_organisation(): void
    {
        $file = $this->exported();
        unset($file['cost_records'][0]['code']);

        $this->send('/imports/canvas/preview', $file)->assertOk()->assertJsonPath('count', 0);
        $this->send('/imports/canvas', $file)->assertOk();

        $this->assertSame(1, CostRecord::whereNull('code')->count());
    }

    public function test_a_reused_cost_record_without_the_application_key_is_reported_as_differing(): void
    {
        $file = $this->exported();
        unset($file['cost_records'][0]['application']);

        $errors = $this->send('/imports/canvas/preview', $file, $this->sameOrganisation())->assertOk()->json('errors');

        $this->assertStringContainsString('verschilt van de bestaande', $errors[0]['message']);
    }

    public function test_an_element_without_a_parent_key_imports(): void
    {
        $file = $this->exported();
        unset($file['canvas']['elements'][$this->elementIndex('Inkoop')]['parent_id']);

        $this->send('/imports/canvas', $file)->assertOk();
    }

    public function test_a_blank_owner_is_refused(): void
    {
        $file = $this->exported();
        $file['cost_records'][0]['owner'] = '   ';

        $this->assertRefusedAt($file, 'cost_records[0].owner');
    }

    public function test_a_statement_body_that_is_null_is_refused(): void
    {
        $file = $this->exported();
        $file['canvas']['statements'][0]['body'] = null;

        $this->assertRefusedAt($file, 'canvas.statements[0].body');
    }

    public function test_blank_cost_record_and_element_names_are_refused(): void
    {
        $file = $this->exported();
        $file['cost_records'][0]['name'] = ' ';
        $file['canvas']['elements'][0]['name'] = "\t";

        $this->assertRefusedAt($file, 'cost_records[0].name');
        $this->assertRefusedAt($file, 'canvas.elements[0].name');
    }

    public function test_a_date_with_an_offset_is_stored_in_utc(): void
    {
        $file = $this->exported();
        $file['canvas']['elements'][$this->elementIndex('Oud')]['archived_at'] = '2026-01-01T10:00:00+05:00';

        $canvasId = $this->send('/imports/canvas', $file)->assertOk()->json('canvas_id');

        $this->assertSame('2026-01-01 05:00:00', DB::table('elements')->where('canvas_id', $canvasId)->where('name', 'Oud')->value('archived_at'));
    }

    public function test_a_relative_date_is_refused(): void
    {
        $file = $this->exported();
        $file['canvas']['elements'][$this->elementIndex('Oud')]['archived_at'] = 'tomorrow';

        $this->assertRefusedAt($file, 'canvas.elements['.$this->elementIndex('Oud').'].archived_at');
    }

    public function test_a_missing_upload_is_refused_with_the_file_message(): void
    {
        $this->post('/imports/canvas', ['organisation_name' => 'X', 'canvas_name' => 'Y'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'Bestand: kies een JSON-export van deze applicatie van maximaal 50 MB');
    }

    public function test_an_oversized_post_is_rendered_with_the_file_message_on_the_import_routes(): void
    {
        $response = app(ExceptionHandler::class)->render(Request::create('/imports/canvas', 'POST'), new PostTooLargeException);

        $this->assertSame(413, $response->getStatusCode());
        $this->assertStringContainsString('maximaal 50 MB', (string) $response->getContent());
    }

    public function test_a_set_for_a_year_without_yearly_figures_is_refused(): void
    {
        $file = $this->exported();
        $file['cost_records'][0]['yearly_figures'] = array_values(array_filter($file['cost_records'][0]['yearly_figures'], fn (array $figure) => $figure['year'] !== 2027));

        $this->assertRefusedAt($file, 'canvas.allocation_sets[2].year');
    }

    public function test_an_active_element_under_an_archived_parent_is_refused(): void
    {
        $file = $this->exported();
        $file['canvas']['elements'][$this->elementIndex('Inkoop')]['archived_at'] = now()->toIso8601String();

        $this->assertRefusedAt($file, 'canvas.elements['.$this->elementIndex('Bestellen').'].parent_id');
    }

    public function test_a_finalised_year_without_a_set_for_every_cost_record_is_refused(): void
    {
        $file = $this->exported();
        $file['canvas']['allocation_sets'] = array_values(array_filter($file['canvas']['allocation_sets'], fn (array $set) => $set['year'] !== 2027 || $set['cost_record_id'] !== $file['cost_records'][1]['id']));

        $this->assertRefusedAt($file, 'canvas.finalised_years[0].year');
    }

    public function test_a_finalised_year_is_refused_when_the_target_holds_a_cost_record_without_a_set(): void
    {
        $target = Organisation::factory()->create();
        $year = FiscalYear::create(['organisation_id' => $target->id, 'year' => 2027]);
        $other = CostRecord::factory()->create(['organisation_id' => $target->id, 'code' => 'ZZ-1']);
        YearlyFigure::factory()->create(['cost_record_id' => $other->id, 'fiscal_year_id' => $year->id]);

        $this->assertRefusedAt($this->exported(), 'canvas.finalised_years[0].year', ['organisation_id' => $target->id, 'organisation_name' => null]);
    }

    public function test_the_dry_run_refuses_an_archived_target_organisation(): void
    {
        $this->canvas->organisation->update(['archived_at' => now()]);

        $this->send('/imports/canvas/preview', $this->exported(), $this->sameOrganisation())->assertStatus(422);
    }
}
