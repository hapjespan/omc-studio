<?php

namespace App\Services\Audit;

use App\Enums\ActorType;
use Closure;
use Illuminate\Support\Facades\Auth;

class AuditContext
{
    private ?string $action = null;

    public function during(string $action, Closure $work): mixed
    {
        $previous = $this->action;
        $this->action = $action;

        try {
            return $work();
        } finally {
            $this->action = $previous;
        }
    }

    public function action(string $default): string
    {
        return $this->action ?? $default;
    }

    public function actorId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    public function actorType(): ActorType
    {
        return $this->actorId() === null ? ActorType::System : ActorType::User;
    }
}
