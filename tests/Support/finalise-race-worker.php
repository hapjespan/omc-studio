<?php

use App\Models\Canvas;
use App\Models\FiscalYear;
use App\Services\Allocation\Finalisation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$canvas = Canvas::query()->with('organisation')->findOrFail((int) $argv[1]);
$year = FiscalYear::query()->findOrFail((int) $argv[2]);

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    $app->make(Finalisation::class)->finalise($canvas, $year);

    echo 'ok';
} catch (ValidationException $refusal) {
    echo implode(' ', $refusal->errors()['rule']);
}
