<?php

use App\Models\User;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->findOrFail((int) $argv[1]);

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    echo $app->make(TotpVerifier::class)->verify($user, (string) $argv[2]) ? 'ok' : 'wrong';
} catch (ValidationException) {
    echo 'refused';
}
