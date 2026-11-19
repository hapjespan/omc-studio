<?php

namespace App\Services\Canvas;

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditRemoval;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganisationService
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly AuditRemoval $removal,
        private readonly AuditContext $context,
    ) {}

    public function create(string $name, bool $sample = false): Organisation
    {
        return $this->writes->run([], function () use ($name, $sample) {
            $this->assertNameFree($name);

            $organisation = new Organisation(['name' => trim($name), 'slug' => $this->uniqueSlug($name)]);
            $organisation->forceFill(['is_sample' => $sample])->save();

            return $organisation;
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

    public function archive(Organisation $organisation): Organisation
    {
        return $this->writes->run([$organisation], function () use ($organisation) {
            if ($organisation->archived_at === null) {
                $this->context->during('archived', fn () => $organisation->update(['archived_at' => now()]));
            }

            return $organisation;
        });
    }

    public function restore(Organisation $organisation): Organisation
    {
        return $this->writes->run([$organisation], function () use ($organisation) {
            if ($organisation->archived_at !== null) {
                $this->context->during('restored', fn () => $organisation->update(['archived_at' => null]));
            }

            return $organisation;
        });
    }

    public function delete(Organisation $organisation): void
    {
        $logo = $this->writes->run([$organisation], function () use ($organisation) {
            if (Canvas::where('organisation_id', $organisation->getKey())->exists() || CostRecord::where('organisation_id', $organisation->getKey())->exists()) {
                throw ValidationException::withMessages(['rule' => __('organisations.delete_blocked')]);
            }

            $path = $organisation->logo_path;

            $organisation->deleteQuietly();
            $this->removal->purge($organisation);

            return $path;
        });

        if (is_string($logo) && $logo !== '') {
            Storage::disk(LogoStore::DISK)->delete($logo);
        }
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
