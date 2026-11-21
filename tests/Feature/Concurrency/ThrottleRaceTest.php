<?php

namespace Tests\Feature\Concurrency;

use App\Services\Accounts\Throttle;
use Illuminate\Support\Str;
use Tests\Support\TwoProcessRunner;
use Tests\TestCase;

class ThrottleRaceTest extends TestCase
{
    public function test_parallel_wrong_attempts_never_evaluate_more_than_the_limit(): void
    {
        $identifier = 'race-'.Str::random(16);

        $outcomes = (new TwoProcessRunner)->run(
            base_path('tests/Support/throttle-worker.php'),
            [$identifier],
            ['CACHE_THROTTLE_STORE' => 'file'],
            8,
        );

        foreach ($outcomes as $outcome) {
            $this->assertMatchesRegularExpression('/^\d+$/', $outcome);
        }

        $this->assertSame(Throttle::LIMIT, array_sum(array_map('intval', $outcomes)));
    }
}
