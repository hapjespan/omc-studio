<?php

namespace App\Support;

use Illuminate\Database\QueryException;

final class QueryTrace
{
    public static function message(QueryException $exception): string
    {
        $root = base_path('app').DIRECTORY_SEPARATOR;

        foreach ($exception->getTrace() as $frame) {
            if (isset($frame['file'], $frame['line']) && str_starts_with($frame['file'], $root)) {
                return $exception->getSql().' at '.substr($frame['file'], strlen(base_path()) + 1).':'.$frame['line'];
            }
        }

        return $exception->getSql();
    }
}
