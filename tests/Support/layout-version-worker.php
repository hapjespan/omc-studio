<?php

use App\Models\Canvas;
use App\Services\Layout\LayoutStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Exceptions\HttpResponseException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$canvas = Canvas::query()->findOrFail((int) $argv[1]);

$payload = [
    'structure_version' => (int) $argv[2],
    'layout_version' => (int) $argv[3],
    'format_version' => 1,
    'areas' => [],
    'elements' => [$argv[4] => ['mode' => 'manual', 'x' => 0, 'y' => 0, 'width' => 200, 'height' => 100]],
];

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    echo 'ok '.$app->make(LayoutStore::class)->save($canvas, $payload);
} catch (HttpResponseException $conflict) {
    echo (string) $conflict->getResponse()->getData()->conflict;
}
