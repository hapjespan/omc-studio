<?php

namespace App\Services\Accounts;

class DeletedUserLabel
{
    public static function for(?int $number): string
    {
        return $number === null ? (string) __('users.deleted_user') : (string) __('users.deleted_user_numbered', ['number' => $number]);
    }
}
