<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    private const TECHNICAL = ['created_at', 'updated_at', 'structure_version', 'layout_version', 'layout_saved_at'];

    private const SECRETS = [
        User::class => [
            'password' => 'password_changed',
            'two_factor_secret' => 'second_factor_changed',
            'two_factor_last_time_step' => null,
        ],
    ];

    public function __construct(private readonly AuditTrail $trail, private readonly AuditContext $context) {}

    public function created(Model $model): void
    {
        $this->write($model, 'created', array_map(fn ($value) => [null, $value], $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        $this->write($model, 'changed', array_combine(
            array_keys($changes),
            array_map(fn ($key, $value) => [$model->getRawOriginal($key), $value], array_keys($changes), $changes),
        ));
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', array_map(fn ($value) => [$value, null], $model->getAttributes()));
    }

    /** @param array<string, array{mixed, mixed}> $changes */
    private function write(Model $model, string $action, array $changes): void
    {
        $secrets = array_intersect_key(self::SECRETS[$model::class] ?? [], $changes);
        $events = array_filter($secrets);
        $changes = array_diff_key($changes, $secrets, array_flip($model->getHidden()), array_flip(self::TECHNICAL), [$model->getKeyName() => null]);

        if ($changes === [] && $events === []) {
            return;
        }

        if ($action === 'changed' && $events !== []) {
            $action = (string) reset($events);
        }

        $this->trail->record($model, $this->context->action($action), $changes);
    }
}
