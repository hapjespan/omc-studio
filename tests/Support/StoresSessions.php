<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait StoresSessions
{
    private function storedSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'payload' => base64_encode((string) json_encode([
                Auth::guard('web')->getName() => $user->getKey(),
                'signed_in_at' => now()->getTimestamp(),
            ])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
