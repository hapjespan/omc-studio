<?php

namespace Tests\Feature\Exchange;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Rules\AmountCents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

class CanvasImportFaultyFilesTest extends CanvasExchangeTestCase
{
    private const REFUSAL = 'Bestand: kies een JSON-export van deze applicatie van maximaal 50 MB';

    protected function setUp(): void
    {
        parent::setUp();

        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());
    }

    /** @return list<int> */
    private function counts(): array
    {
        return [Organisation::count(), Canvas::count(), Element::count(), CostRecord::count(), YearlyFigure::count(), FiscalYear::count(), FinalisedYear::count(), AuditEntry::count()];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return TestResponse<JsonResponse>
     */
    private function send(string $path, string $content, array $fields = []): TestResponse
    {
        return $this->post($path, ['file' => UploadedFile::fake()->createWithContent('canvas.json', $content), 'organisation_name' => 'Nieuwe organisatie', 'canvas_name' => 'Nieuw', ...$fields], ['Accept' => 'application/json']);
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  array<string, mixed>  $fields
     */
    private function assertRefusedAt(array $file, string $location, array $fields = []): void
    {
        $content = (string) json_encode($file);
        $before = $this->counts();

        $locations = array_column($this->send('/imports/canvas/preview', $content, $fields)->assertOk()->json('errors'), 'location');
        $this->assertContains($location, $locations);

        $this->send('/imports/canvas', $content, $fields)->assertStatus(422);
        $this->assertSame($before, $this->counts());
    }

    private function assertFatal(string $content): void
    {
        $before = $this->counts();

        foreach (['/imports/canvas/preview', '/imports/canvas'] as $path) {
            $this->send($path, $content)->assertStatus(422)->assertJsonPath('errors.file.0', self::REFUSAL);
        }

        $this->assertSame($before, $this->counts());
    }

    public function test_an_unknown_format_version_is_refused(): void
    {
        $this->assertFatal((string) json_encode([...$this->exported(), 'format_version' => 2]));
    }

    public function test_content_that_is_not_json_is_refused(): void
    {
        $this->assertFatal('not json at all');
    }

    public function test_a_file_over_50_megabytes_is_refused(): void
    {
        $this->assertFatal(str_repeat(' ', 52_428_801));
    }

    public function test_a_json_list_instead_of_an_export_is_refused(): void
    {
        $this->assertFatal('[1,2,3]');
    }

    public function test_a_canvas_name_over_150_characters_is_reported_with_its_location(): void
    {
        $file = $this->exported();
        $file['canvas']['name'] = str_repeat('a', 151);

        $this->assertRefusedAt($file, 'canvas.name');
    }

    public function test_an_element_name_with_a_greater_than_sign_is_reported(): void
    {
        $file = $this->exported();
        $file['canvas']['elements'][0]['name'] = 'Een > twee';

        $this->assertRefusedAt($file, 'canvas.elements[0].name');
    }

    public function test_run_plus_change_above_100_percent_is_reported(): void
    {
        $file = $this->exported();
        $file['cost_records'][0]['yearly_figures'][0]['run_bp'] = 6000;
        $file['cost_records'][0]['yearly_figures'][0]['change_bp'] = 4001;

        $this->assertRefusedAt($file, 'cost_records[0].yearly_figures[0].change_bp');
    }

    public function test_an_amount_out_of_range_is_reported(): void
    {
        $file = $this->exported();
        $file['cost_records'][0]['yearly_figures'][0]['budget_cents'] = AmountCents::MAX_CENTS + 1;

        $this->assertRefusedAt($file, 'cost_records[0].yearly_figures[0].budget_cents');
    }

    public function test_a_colour_that_is_not_rrggbb_is_reported(): void
    {
        $file = $this->exported();
        $id = Element::where('canvas_id', $this->canvas->id)->where('name', 'Inkoop')->value('id');
        $every = array_fill_keys(array_column(array_filter($file['canvas']['elements'], fn (array $element) => $element['archived_at'] === null), 'id'), ['mode' => 'automatic']);
        $file['canvas']['layout'] = ['areas' => [], 'elements' => [$id => ['mode' => 'manual', 'x' => 0, 'y' => 0, 'width' => 200, 'height' => 100, 'style' => ['fill' => 'red']]] + $every];

        $this->assertRefusedAt($file, "canvas.layout.elements.$id.style");
    }

    public function test_lines_that_do_not_make_100_percent_are_reported(): void
    {
        $file = $this->exported();
        $file['canvas']['allocation_sets'][0]['lines'][0]['basis_points'] = 1;

        $this->assertRefusedAt($file, 'canvas.allocation_sets[0]');
    }

    public function test_a_file_without_cost_records_gives_a_located_error_not_a_crash(): void
    {
        $file = $this->exported();
        unset($file['cost_records']);

        $this->assertRefusedAt($file, 'cost_records');
    }

    public function test_a_canvas_name_taken_in_the_target_is_reported(): void
    {
        $this->assertRefusedAt($this->exported(), 'canvas_name', ['organisation_id' => $this->canvas->organisation_id, 'organisation_name' => null, 'canvas_name' => 'Basis canvas']);
    }

    public function test_a_cost_record_that_differs_from_the_existing_one_is_reported(): void
    {
        $file = $this->exported();
        $file['cost_records'][0]['name'] = 'Anders';

        $this->assertRefusedAt($file, 'cost_records[0]', ['organisation_id' => $this->canvas->organisation_id, 'organisation_name' => null, 'canvas_name' => 'Kopie']);
    }

    public function test_figures_for_a_year_finalised_in_the_target_are_reported(): void
    {
        $target = Organisation::factory()->create();
        $year = FiscalYear::create(['organisation_id' => $target->id, 'year' => 2027]);
        $other = Canvas::factory()->create(['organisation_id' => $target->id]);
        FinalisedYear::create(['canvas_id' => $other->id, 'fiscal_year_id' => $year->id, 'finalised_at' => now()]);

        $this->assertRefusedAt($this->exported(), 'cost_records[0].yearly_figures[1].year', ['organisation_id' => $target->id, 'organisation_name' => null, 'canvas_name' => 'Kopie']);
    }
}
