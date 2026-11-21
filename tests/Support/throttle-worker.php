<?php

use App\Services\Accounts\Throttle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$throttle = new Throttle('race');
$identifier = $argv[1];
$evaluated = 0;

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    for ($attempt = 0; $attempt < 6; $attempt++) {
        if ($throttle->claim($identifier)) {
            $evaluated++;
            $throttle->fail($identifier);
        }
    }
} catch (Throwable $failure) {
    fwrite(STDERR, $failure->getMessage());
    exit(1);
}

echo $evaluated;
