<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionTerminator
{
    public function endAll(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->getKey())->delete();
    }

    public function endOthers(User $user, string $currentSessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
