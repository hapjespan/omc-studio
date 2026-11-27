<?php

namespace App\Models;

use App\Enums\Role;
use App\Services\Audit\Audited;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

#[Fillable([
    'name', 'email', 'password', 'role', 'must_change_password', 'one_time_password_expires_at',
    'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_time_step',
])]
#[Hidden(['password', 'two_factor_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasFactory;

    protected $casts = [
        'password' => 'hashed',
        'role' => Role::class,
        'must_change_password' => 'boolean',
        'one_time_password_expires_at' => 'datetime',
        'two_factor_secret' => 'encrypted',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_last_time_step' => 'integer',
    ];

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtolower(trim($value)));
    }

    public function hasLegacyPassword(): bool
    {
        return str_starts_with((string) $this->password, '$2y$');
    }

    public function reusesPassword(string $plain): bool
    {
        if ($this->hasLegacyPassword() && strlen($plain) > 72) {
            return false;
        }

        return Hash::check($plain, (string) $this->password);
    }
}
