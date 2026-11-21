<?php

use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\OrganisationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$organisation = Organisation::query()->findOrFail((int) $argv[1]);
$template = Canvas::query()->findOrFail((int) $argv[2]);
$deleting = @fopen($argv[3], 'x') !== false;

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    $deleting
        ? $app->make(OrganisationService::class)->delete($organisation)
        : $app->make(CanvasLifecycle::class)->createFromTemplate($organisation, $template, 'Race canvas');

    echo ($deleting ? 'delete' : 'create').':ok';
} catch (ValidationException $refusal) {
    echo ($deleting ? 'delete' : 'create').':refused:'.implode(' ', array_map(fn ($messages) => implode(' ', (array) $messages), $refusal->errors()));
} catch (Throwable $failure) {
    echo ($deleting ? 'delete' : 'create').':failed:'.class_basename($failure);
}
