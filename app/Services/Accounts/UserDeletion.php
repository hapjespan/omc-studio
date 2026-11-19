<?php

namespace App\Services\Accounts;

use App\Models\AuditEntry;
use App\Models\User;
use App\Services\Audit\SecurityLog;
use App\Services\Support\SerialisedWrite;
use Illuminate\Support\Facades\Auth;

class UserDeletion
{
    private const IDENTIFYING = ['name', 'email'];

    public function __construct(
        private readonly AdministratorGuard $guard,
        private readonly SessionTerminator $sessions,
        private readonly SerialisedWrite $writes,
    ) {}

    public function delete(User $user, ?User $actor = null): void
    {
        $actor ??= Auth::user();

        $this->writes->run($this->guard->aggregates($user), function () use ($user, $actor): void {
            $this->guard->assertCanDelete($actor instanceof User ? $actor : null, $user);

            $identifier = (int) $user->getKey();
            $ended = $this->sessions->endAll($user);
            $user->delete();

            $this->pseudonymise($identifier, [$user->name, $user->email]);

            SecurityLog::record('user_deleted', ['subject_id' => $identifier, 'sessions_ended' => $ended]);
        });
    }

    /** @param array<int, string> $known */
    private function pseudonymise(int $identifier, array $known): void
    {
        $pseudonym = (string) __('users.deleted_user');
        $needles = $this->needles($identifier, $known);

        AuditEntry::query()->where('actor_id', $identifier)->update(['actor_id' => null]);

        AuditEntry::query()->lazyById()->each(function (AuditEntry $entry) use ($needles, $pseudonym): void {
            $label = str_ireplace($needles, $pseudonym, (string) $entry->subject_label);
            $changes = $this->replace($entry->changes, $needles, $pseudonym);

            if ($label === $entry->subject_label && $changes === $entry->changes) {
                return;
            }

            $entry->forceFill(['subject_label' => $label, 'changes' => $changes])->save();
        });
    }

    /**
     * @param  array<int, string>  $known
     * @return array<int, string>
     */
    private function needles(int $identifier, array $known): array
    {
        $entries = AuditEntry::query()->where('subject_type', 'user')->where('subject_id', (string) $identifier)->get();

        foreach ($entries as $entry) {
            $known[] = (string) $entry->subject_label;

            foreach (self::IDENTIFYING as $key) {
                $known = [...$known, ...array_filter($entry->changes[$key] ?? [], 'is_string')];
            }
        }

        $needles = array_values(array_unique(array_filter($known, fn (string $needle) => $needle !== '')));
        usort($needles, fn (string $first, string $second) => mb_strlen($second) <=> mb_strlen($first));

        return $needles;
    }

    /** @param array<int, string> $needles */
    private function replace(mixed $value, array $needles, string $pseudonym): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->replace($item, $needles, $pseudonym), $value);
        }

        return is_string($value) ? str_ireplace($needles, $pseudonym, $value) : $value;
    }
}
