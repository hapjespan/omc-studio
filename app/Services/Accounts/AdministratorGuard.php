<?php

namespace App\Services\Accounts;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdministratorGuard
{
    /** @return array<int, User> */
    public function aggregates(User $target): array
    {
        return [$target, ...User::query()->where('role', Role::Administrator->value)->whereKeyNot($target->getKey())->get()->all()];
    }

    public function assertCanChangeRole(?User $actor, User $target, Role $role): void
    {
        if ($role === $target->role) {
            return;
        }

        $this->assertNotSelf($actor, $target);
        $this->assertAnotherAdministratorRemains($target);
    }

    public function assertCanDelete(?User $actor, User $target): void
    {
        $this->assertNotSelf($actor, $target);
        $this->assertAnotherAdministratorRemains($target);
    }

    private function assertNotSelf(?User $actor, User $target): void
    {
        if ($actor !== null && $actor->is($target)) {
            $this->refuse('own_account');
        }
    }

    private function assertAnotherAdministratorRemains(User $target): void
    {
        if ($target->role !== Role::Administrator) {
            return;
        }

        if (User::query()->where('role', Role::Administrator->value)->whereKeyNot($target->getKey())->doesntExist()) {
            $this->refuse('last_administrator');
        }
    }

    private function refuse(string $rule): never
    {
        throw ValidationException::withMessages(['rule' => __('users.'.$rule)]);
    }
}
