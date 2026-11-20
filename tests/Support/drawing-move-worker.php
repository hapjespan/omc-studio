<?php

use App\Models\Canvas;
use App\Services\Layout\DrawingMove;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Exceptions\HttpResponseException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$canvas = Canvas::query()->findOrFail((int) $argv[1]);

$request = [
    'element_id' => $argv[4],
    'target_id' => $argv[5],
    'structure_version' => (int) $argv[2],
    'layout_version' => (int) $argv[3],
    'layout' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 100, 'z' => 0, 'style' => []],
];

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    echo 'ok '.$app->make(DrawingMove::class)->move($canvas, $request)['structure_version'];
} catch (HttpResponseException $conflict) {
    echo (string) json_decode((string) $conflict->getResponse()->getContent(), true)['conflict'];
}
