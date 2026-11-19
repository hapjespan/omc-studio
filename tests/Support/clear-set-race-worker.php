<?php

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Allocation\AllocationSets;
use App\Services\Allocation\Finalisation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$finalises = (@fopen($argv[5], 'x') !== false) === ($argv[6] === 'finalise');

$canvas = Canvas::query()->with('organisation')->findOrFail((int) $argv[1]);
$year = FiscalYear::query()->findOrFail((int) $argv[2]);
$record = CostRecord::query()->with('organisation')->findOrFail($argv[3]);
$user = User::query()->findOrFail($argv[4]);

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

if ($finalises && $argv[6] === 'clear') {
    usleep(50_000);
}

try {
    if ($finalises) {
        $app->make(Finalisation::class)->finalise($canvas, $year);
    } else {
        $app->make(AllocationSets::class)->clear($canvas, $record, $year, $user);
    }

    echo 'ok';
} catch (ValidationException $refusal) {
    echo implode(' ', $refusal->errors()['rule']);
}
