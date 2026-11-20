<?php

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Services\Allocation\Finalisation;
use App\Services\Finance\YearlyFigures;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$finalises = @fopen($argv[4], 'x') !== false;

$canvas = Canvas::query()->with('organisation')->findOrFail((int) $argv[1]);
$year = FiscalYear::query()->findOrFail((int) $argv[2]);
$record = CostRecord::query()->with('organisation')->findOrFail($argv[3]);

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    if ($finalises) {
        $app->make(Finalisation::class)->finalise($canvas, $year);
    } else {
        $app->make(YearlyFigures::class)->save($record, $year, ['budget' => '1.000,00']);
    }

    echo 'ok';
} catch (ValidationException $refusal) {
    echo implode(' ', $refusal->errors()['rule']);
}
