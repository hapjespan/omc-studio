<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

ini_set('display_errors', 'stderr');

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    eval((string) stream_get_contents(STDIN));
} catch (Throwable $failure) {
    fwrite(STDERR, (string) $failure);
    exit(1);
}
