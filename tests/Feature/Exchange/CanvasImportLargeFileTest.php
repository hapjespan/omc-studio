<?php

namespace Tests\Feature\Exchange;

use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Exchange\CanvasImportValidator;
use App\Services\Structure\ElementFields;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CanvasImportLargeFileTest extends ExchangeTestCase
{
    /** @var list<int> */
    private array $levels = [];

    public function test_a_file_of_almost_50_megabytes_imports_within_60_seconds_with_validation_outside_the_transaction(): void
    {
        ini_set('memory_limit', '1G');
        set_time_limit(0);

        $this->richCanvas();
        $file = $this->exported();
        $parent = array_column($file['canvas']['elements'], 'id', 'name')['Inkoop'];
        $text = str_repeat('x', 1900);

        for ($i = 0; $i < 23_000; $i++) {
            $file['canvas']['elements'][] = ['id' => 'bulk-'.$i, 'parent_id' => $parent, 'area' => 'primary_activities', 'kind' => 'activity', 'name' => 'Bulk '.$i, 'description' => $text, 'modality' => null, 'rationale' => null, 'icon' => null, 'position' => $i + 10, 'is_unknown_allocation' => false, 'archived_at' => null];
        }

        $content = (string) json_encode($file);
        unset($file);
        $this->assertGreaterThan(40_000_000, strlen($content));
        $this->assertLessThanOrEqual(CanvasImportValidator::MAX_BYTES, strlen($content));

        $baseline = DB::transactionLevel();
        $this->app->bind(CanvasImportValidator::class, fn ($app) => new class($app->make(ElementFields::class), function () {
            $this->levels[] = DB::transactionLevel();
        }) extends CanvasImportValidator
        {

            public function __construct(ElementFields $fields, private readonly \Closure $probe)
            {
                parent::__construct($fields);
            }

            public function check(string $content, ?Organisation $target, string $canvasName, ?string $organisationName = null): array
            {
                ($this->probe)();

                return parent::check($content, $target, $canvasName, $organisationName);
            }
        });

        $this->actingAs(User::factory()->administrator()->create());
        $started = microtime(true);

        $this->post('/imports/canvas', [
            'file' => UploadedFile::fake()->createWithContent('canvas.json', $content),
            'organisation_name' => 'Groot',
            'canvas_name' => 'Groot canvas',
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertLessThan(60, microtime(true) - $started);
        $this->assertSame([$baseline], $this->levels);
        $this->assertSame(23_000, Element::where('name', 'like', 'Bulk %')->where('canvas_id', '!=', $this->canvas->id)->count());
    }
}
