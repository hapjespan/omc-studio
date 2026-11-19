<?php

use App\Models\User;
use App\Services\Accounts\UserAdministration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$identifiers = [(int) $argv[1], (int) $argv[2]];
$position = @fopen($argv[3], 'x') === false ? 1 : 0;

$actor = User::query()->findOrFail($identifiers[$position]);
$target = User::query()->findOrFail($identifiers[1 - $position]);

$startAt = (float) getenv('RACE_START_AT');

if ($startAt > microtime(true)) {
    time_sleep_until($startAt);
}

try {
    $app->make(UserAdministration::class)->update($target, [
        'name' => (string) $target->name,
        'email' => (string) $target->email,
        'role' => 'reader',
    ], $actor);

    echo 'ok';
} catch (ValidationException $refusal) {
    echo implode(' ', $refusal->errors()['rule']);
}
