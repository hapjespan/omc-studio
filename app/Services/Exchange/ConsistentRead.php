<?php

namespace App\Services\Exchange;

use Closure;
use Illuminate\Support\Facades\DB;

class ConsistentRead
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $read
     * @return TReturn
     */
    public function run(Closure $read): mixed
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            return $read();
        }

        $pdo = $connection->getPdo();
        $pdo->exec($connection->getDriverName() === 'pgsql' ? 'BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY' : 'BEGIN');

        try {
            return $read();
        } finally {
            $pdo->exec('ROLLBACK');
        }
    }
}
