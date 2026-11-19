<?php

namespace App\Services\Finance;

use App\Models\Application;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use App\Services\Undo\UndoStore;
use Illuminate\Validation\ValidationException;

class OrganisationLists
{
    use WritableGuard;

    public const NO_APPLICATION = 'none';

    public function __construct(private readonly SerialisedWrite $writes, private readonly UndoStore $undo) {}

    /**
     * @param  class-string<Owner|Application>  $model
     * @param  array<string, mixed>  $data
     */
    public function create(Organisation $organisation, string $model, array $data): Owner|Application
    {
        return $this->writes->run([$organisation], function () use ($organisation, $model, $data) {
            $this->assertWritable($organisation);
            $this->assertNameFree($organisation, $model, (string) $data['name']);

            return $model::create(['organisation_id' => $organisation->getKey()] + $data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Organisation $organisation, Owner|Application $entry, array $data): Owner|Application
    {
        return $this->writes->run([$organisation], function () use ($organisation, $entry, $data) {
            $this->assertWritable($organisation);
            $this->assertNameFree($organisation, $entry::class, (string) $data['name'], $entry);
            $entry->update($data);

            return $entry;
        });
    }

    public function delete(Organisation $organisation, Owner|Application $entry, User $user, ?string $replaceWith = null): ?string
    {
        return $this->writes->run([$organisation], function () use ($organisation, $entry, $user, $replaceWith) {
            $this->assertWritable($organisation);

            if ($replaceWith !== null) {
                $this->reassign($organisation, $entry, $replaceWith);
                $entry->delete();

                return null;
            }

            if ($this->inUse($entry)) {
                throw ValidationException::withMessages(['rule' => __('lists.in_use')]);
            }

            $row = $entry->getAttributes();
            $entry->delete();

            return $this->undo->remember(
                $entry instanceof Owner ? UndoStore::OWNER_DELETION : UndoStore::APPLICATION_DELETION,
                ['entry' => $row],
                $user,
            );
        });
    }

    private function reassign(Organisation $organisation, Owner|Application $entry, string $replaceWith): void
    {
        $column = $entry instanceof Owner ? 'owner_id' : 'application_id';
        $replacement = $this->replacement($organisation, $entry, $replaceWith);
        $records = CostRecord::where($column, $entry->getKey())->get();

        foreach ($records as $record) {
            $record->update([$column => $replacement]);
        }
    }

    private function replacement(Organisation $organisation, Owner|Application $entry, string $replaceWith): ?int
    {
        if ($replaceWith === self::NO_APPLICATION) {
            if ($entry instanceof Owner) {
                $this->refuse('replacement_invalid');
            }

            return null;
        }

        $replacement = $entry->newQuery()
            ->where('organisation_id', $organisation->getKey())
            ->whereKeyNot($entry->getKey())
            ->find($replaceWith);

        if ($replacement === null) {
            $this->refuse('replacement_invalid');
        }

        return (int) $replacement->getKey();
    }

    private function inUse(Owner|Application $entry): bool
    {
        return CostRecord::where($entry instanceof Owner ? 'owner_id' : 'application_id', $entry->getKey())->exists();
    }

    /** @param class-string<Owner|Application> $model */
    private function assertNameFree(Organisation $organisation, string $model, string $name, Owner|Application|null $except = null): void
    {
        $taken = $model::where('organisation_id', $organisation->getKey())
            ->where('name_normalised', $model::normalise($name))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('lists.name_taken')]);
        }
    }
}
