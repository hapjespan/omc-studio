<?php

namespace App\Services\Finance;

use App\Models\Application;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\Owner;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class CostRecords
{
    use WritableGuard;

    public function __construct(private readonly SerialisedWrite $writes) {}

    /** @param array<string, mixed> $data */
    public function create(Organisation $organisation, array $data): CostRecord
    {
        return $this->writes->run([$organisation], function () use ($organisation, $data) {
            $this->assertWritable($organisation);
            $this->assertValid($organisation, $data);

            return $organisation->costRecords()->create($data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CostRecord $record, array $data): CostRecord
    {
        return $this->writes->run([$record->organisation], function () use ($record, $data) {
            $this->assertWritable($record->organisation);
            $this->assertValid($record->organisation, $data, $record);
            $record->update($data);

            return $record;
        });
    }

    /** @param array<string, mixed> $data */
    private function assertValid(Organisation $organisation, array $data, ?CostRecord $except = null): void
    {
        $errors = [];

        if (Owner::where('organisation_id', $organisation->getKey())->whereKey($data['owner_id'])->doesntExist()) {
            $errors['owner_id'] = __('cost_records.owner_invalid');
        }

        if (isset($data['application_id']) && Application::where('organisation_id', $organisation->getKey())->whereKey($data['application_id'])->doesntExist()) {
            $errors['application_id'] = __('cost_records.application_invalid');
        }

        $code = trim((string) ($data['code'] ?? ''));

        if ($code !== '' && CostRecord::where('organisation_id', $organisation->getKey())->where('code_normalised', mb_strtolower($code))->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))->exists()) {
            $errors['code'] = __('cost_records.code_taken');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
