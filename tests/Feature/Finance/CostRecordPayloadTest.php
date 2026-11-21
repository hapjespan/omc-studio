<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Database\Factories\PerformanceDatasetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CostRecordPayloadTest extends TestCase
{
    use RefreshDatabase;

    private const FIRST_RESPONSE_ROW_BYTES = 60_000;

    private const NEVER_READ = ['run_bp', 'change_bp', 'fte_hundredths', 'reference_date', 'remark'];

    public function test_a_row_carries_none_of_the_raw_columns_the_page_never_reads(): void
    {
        app(ReferenceDataStep::class)();
        $data = (new PerformanceDatasetFactory(10, 5, 2026, 11))->create();
        $this->actingAs(User::factory()->administrator()->create());

        foreach (['/canvases/'.$data['canvas']->id.'/cost-records?year=2026', '/organisations/'.$data['organisation']->id.'/cost-records?year=2026'] as $url) {
            $this->get($url)->assertInertia(function (Assert $page) {
                $page->has('rows.0.figures.run')->has('rows.0.figures.reference_date')->has('rows.0.figures.remark')->has('rows.0.budget')->has('rows.0.budget_cents');

                foreach (self::NEVER_READ as $column) {
                    $page->missing('rows.0.'.$column);
                }
            });
        }
    }

    public function test_the_first_response_rows_of_the_p1_dataset_stay_under_the_payload_budget(): void
    {
        app(ReferenceDataStep::class)();
        $data = (new PerformanceDatasetFactory(500, 1000, 2026, 7))->create();
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/canvases/'.$data['canvas']->id.'/cost-records?year=2026')->assertInertia(function (Assert $page) {
            $rows = $page->toArray()['props']['rows'];

            $this->assertCount(60, $rows);
            $page->where('count', 1000);
            $this->assertLessThan(self::FIRST_RESPONSE_ROW_BYTES, strlen((string) json_encode($rows)));
        });
    }

    public function test_the_first_response_carries_the_first_chunk_and_one_deferred_load_the_rest_in_order(): void
    {
        app(ReferenceDataStep::class)();
        $data = (new PerformanceDatasetFactory(20, 150, 2026, 5))->create();
        $this->actingAs(User::factory()->administrator()->create());
        $url = '/organisations/'.$data['organisation']->id.'/cost-records?year=2026';
        $names = [];

        $this->get($url)->assertInertia(function (Assert $page) use (&$names) {
            $first = $page->toArray()['props']['rows'];
            $page->where('count', 150)->missing('moreRows');
            $this->assertCount(60, $first);
            $names = array_column($first, 'name');
        });

        $this->get($url)->assertInertia(fn (Assert $page) => $page->loadDeferredProps(function (Assert $loaded) use (&$names) {
            $rest = $loaded->toArray()['props']['moreRows'];
            $this->assertCount(90, $rest);
            $all = array_merge($names, array_column($rest, 'name'));
            $sorted = $all;
            usort($sorted, fn (string $a, string $b) => strcasecmp($a, $b));
            $this->assertSame($sorted, $all);
        }));
    }

    public function test_a_list_within_the_first_chunk_has_an_empty_deferred_tail(): void
    {
        app(ReferenceDataStep::class)();
        $data = (new PerformanceDatasetFactory(20, 30, 2026, 5))->create();
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/organisations/'.$data['organisation']->id.'/cost-records?year=2026')->assertInertia(function (Assert $page) {
            $page->where('count', 30);
            $this->assertCount(30, $page->toArray()['props']['rows']);
            $page->loadDeferredProps(fn (Assert $loaded) => $loaded->has('moreRows', 0));
        });
    }
}
