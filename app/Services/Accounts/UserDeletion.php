<?php

namespace App\Services\Accounts;

use App\Models\AuditEntry;
use App\Models\FinalisedYear;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Audit\SecurityLog;
use App\Services\Support\SerialisedWrite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
            $this->number($identifier);
            $ended = $this->sessions->endAll($user);
            $user->delete();

            $this->pseudonymise($identifier, [$user->name, $user->email]);

            SecurityLog::record('user_deleted', ['subject_id' => $identifier, 'sessions_ended' => $ended]);
        });
    }

    private function number(int $identifier): void
    {
        foreach ([[AuditEntry::class, 'actor_id'], [FinalisedYear::class, 'finalised_by'], [Snapshot::class, 'created_by']] as [$model, $column]) {
            $model::query()->where($column, $identifier)->update(['deleted_user_number' => $identifier]);
        }
    }

    /** @param array<int, string> $known */
    private function pseudonymise(int $identifier, array $known): void
    {
        $pseudonym = DeletedUserLabel::for($identifier);
        $patterns = $this->patterns($identifier, $known);

        AuditEntry::query()
            ->where(fn ($query) => $query->where('deleted_user_number', $identifier)->orWhere(fn ($about) => $about->where('subject_type', 'user')->where('subject_id', (string) $identifier)))
            ->lazyById()->each(function (AuditEntry $entry) use ($patterns, $pseudonym): void {
                $label = Str::limit((string) $this->replace((string) $entry->subject_label, $patterns, $pseudonym), 150, '');
                $changes = $this->replace($entry->changes, $patterns, $pseudonym);

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
    private function patterns(int $identifier, array $known): array
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

        return array_map(fn (string $needle) => '/(?<![\p{L}\p{N}@.])'.preg_quote($needle, '/').'(?![\p{L}\p{N}@])/iu', $needles);
    }

    /** @param array<int, string> $patterns */
    private function replace(mixed $value, array $patterns, string $pseudonym): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->replace($item, $patterns, $pseudonym), $value);
        }

        return is_string($value) ? (string) preg_replace_callback($patterns, fn () => $pseudonym, $value) : $value;
    }
}
