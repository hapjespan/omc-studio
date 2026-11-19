<?php

use App\Models\Organisation;
use App\Services\Support\SerialisedWrite;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$organisation = Organisation::query()->findOrFail((int) $argv[1]);

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    $app->make(SerialisedWrite::class)->run([$organisation], function () use ($organisation): void {
        if ($organisation->archived_at !== null) {
            throw new RuntimeException('refused');
        }

        $organisation->update(['archived_at' => now()]);
    });

    echo 'ok';
} catch (RuntimeException $exception) {
    echo $exception->getMessage();
}
