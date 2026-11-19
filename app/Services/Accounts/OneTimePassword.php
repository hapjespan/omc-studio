<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Carbon;

class OneTimePassword
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';

    private const LENGTH = 16;

    private const HOURS = 24;

    public function generate(): string
    {
        $password = '';

        for ($character = 0; $character < self::LENGTH; $character++) {
            $password .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $password;
    }

    public function issueTo(User $user): string
    {
        $password = $this->generate();

        $user->fill([
            'password' => $password,
            'must_change_password' => true,
            'one_time_password_expires_at' => Carbon::now()->addHours(self::HOURS),
        ])->save();

        return $password;
    }

    public function isExpired(User $user): bool
    {
        return $user->one_time_password_expires_at !== null && $user->one_time_password_expires_at->isPast();
    }
}
