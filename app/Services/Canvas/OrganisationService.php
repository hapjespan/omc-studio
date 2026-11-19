<?php

namespace App\Services\Canvas;

use App\Models\Organisation;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganisationService
{
    use WritableGuard;

    public function __construct(private readonly SerialisedWrite $writes) {}

    public function create(string $name): Organisation
    {
        return $this->writes->run([], function () use ($name) {
            $this->assertNameFree($name);

            return Organisation::create(['name' => trim($name), 'slug' => $this->uniqueSlug($name)]);
        });
    }

    public function rename(Organisation $organisation, string $name): Organisation
    {
        return $this->writes->run([$organisation], function () use ($organisation, $name) {
            $this->assertWritable($organisation);
            $this->assertNameFree($name, $organisation);
            $organisation->update(['name' => trim($name)]);

            return $organisation;
        });
    }

    private function assertNameFree(string $name, ?Organisation $except = null): void
    {
        $taken = Organisation::where('name_normalised', Organisation::normalise($name))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('organisations.name_taken')]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organisation';
        $slug = $base;

        for ($suffix = 2; Organisation::where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
