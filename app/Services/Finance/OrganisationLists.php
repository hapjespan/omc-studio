<?php

namespace App\Services\Finance;

use App\Models\Application;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\Owner;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class OrganisationLists
{
    use WritableGuard;

    public function __construct(private readonly SerialisedWrite $writes) {}

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

    public function delete(Organisation $organisation, Owner|Application $entry): void
    {
        $this->writes->run([$organisation], function () use ($organisation, $entry) {
            $this->assertWritable($organisation);

            if ($this->inUse($entry)) {
                throw ValidationException::withMessages(['rule' => __('lists.in_use')]);
            }

            $entry->delete();
        });
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
