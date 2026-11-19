<?php

namespace Tests\Feature\Exchange;

use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CostRecordImportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

    private const TARGET_BYTES = 5_000_000;

    private const LIMIT_SECONDS = 10.0;

    public function test_dry_run_and_commit_of_a_five_megabyte_file_finish_within_ten_seconds(): void
    {
        set_time_limit(0);
        $organisation = Organisation::factory()->create();
        FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);
        $this->actingAs(User::factory()->administrator()->create());

        [$content, $lines] = $this->content();
        $this->assertGreaterThanOrEqual(self::TARGET_BYTES, strlen($content));
        $this->assertLessThanOrEqual(5_242_880, strlen($content));

        $preview = $this->timed(fn () => $this->send($organisation, 'import/preview', $content));
        $commit = $this->timed(fn () => $this->send($organisation, 'import', $content));

        fwrite(STDERR, sprintf(
            "\nP-3 %d lines, %d bytes: dry run %.2f s, commit %.2f s on %s, %s cores, PHP %s\n",
            $lines, strlen($content), $preview, $commit, php_uname('s').' '.php_uname('r'), trim((string) shell_exec('nproc')), PHP_VERSION,
        ));

        $this->assertSame($lines, CostRecord::count());
        $this->assertLessThan(self::LIMIT_SECONDS, $preview);
        $this->assertLessThan(self::LIMIT_SECONDS, $commit);
    }

    /** @return array{0: string, 1: int} */
    private function content(): array
    {
        $content = self::HEADER."\n";
        $lines = 0;

        while (strlen($content) < self::TARGET_BYTES) {
            $content .= sprintf("P3-%06d;Kostenpost %06d;Leverancierscontract;Leverancier;Ada;KP-1;Applicatie;2026;%d,50;;60;30;1,5\n", $lines, $lines, $lines % 1000);
            $lines++;
        }

        return [$content, $lines];
    }

    /** @return TestResponse<JsonResponse> */
    private function send(Organisation $organisation, string $path, string $content): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        return $this->post('/organisations/'.$organisation->id.'/cost-records/'.$path, ['file' => $file], ['Accept' => 'application/json'])->assertOk();
    }

    private function timed(callable $work): float
    {
        $start = microtime(true);
        $work();

        return microtime(true) - $start;
    }
}
