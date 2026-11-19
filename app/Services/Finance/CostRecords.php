<?php

namespace App\Services\Finance;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Application;
use App\Models\CostRecord;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Services\Allocation\FinalisedYears;
use App\Services\Audit\AuditContext;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use App\Services\Undo\UndoStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CostRecords
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly FinalisedYears $finalisedYears,
        private readonly UndoStore $undo,
        private readonly AuditContext $context,
    ) {}

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
            $this->assertCostRecordActive($record);
            $this->assertValid($record->organisation, $data, $record);
            $record->update($data);

            return $record;
        });
    }

    public function delete(CostRecord $record, User $user): string
    {
        return $this->writes->run([$record->organisation], function () use ($record, $user) {
            $this->assertWritable($record->organisation);
            $this->assertEveryYearOpen($record);

            return $this->undo->remember(UndoStore::COST_RECORD_DELETION, $this->remove($record->organisation, [$record]), $user);
        });
    }

    /**
     * @param  Collection<int, CostRecord>  $records
     * @return array{token: string|null, done: int, skipped: list<array{name: string, reason: string}>}
     */
    public function deleteMany(Organisation $organisation, Collection $records, User $user): array
    {
        return $this->writes->run([$organisation], function () use ($organisation, $records, $user) {
            $this->assertWritable($organisation);

            $deletable = [];
            $skipped = [];

            foreach ($records as $record) {
                try {
                    $this->assertEveryYearOpen($record);
                    $deletable[] = $record;
                } catch (ValidationException $refusal) {
                    $skipped[] = ['name' => (string) $record->name, 'reason' => (string) $refusal->validator->errors()->first()];
                }
            }

            $token = $deletable === [] ? null : $this->undo->remember(UndoStore::COST_RECORD_DELETION, $this->remove($organisation, $deletable), $user);

            return ['token' => $token, 'done' => count($deletable), 'skipped' => $skipped];
        });
    }

    public function archive(CostRecord $record): CostRecord
    {
        return $this->setArchivedAt($record, now(), 'archived');
    }

    public function restore(CostRecord $record): CostRecord
    {
        return $this->setArchivedAt($record, null, 'restored');
    }

    /** @return array{figures: int, sets: int} */
    public function counts(CostRecord $record): array
    {
        return ['figures' => $record->yearlyFigures()->count(), 'sets' => $this->sets($record)->count()];
    }

    private function setArchivedAt(CostRecord $record, ?Carbon $moment, string $action): CostRecord
    {
        return $this->writes->run([$record->organisation], function () use ($record, $moment, $action) {
            $this->assertWritable($record->organisation);

            if (($record->archived_at === null) !== ($moment === null)) {
                $this->context->during($action, fn () => $record->update(['archived_at' => $moment]));
            }

            return $record;
        });
    }

    private function assertEveryYearOpen(CostRecord $record): void
    {
        foreach ($record->yearlyFigures()->with('fiscalYear')->get() as $figure) {
            $canvas = $this->finalisedYears->finalisedInOrganisation($figure->fiscalYear);

            if ($canvas !== null) {
                throw ValidationException::withMessages([
                    'rule' => __('rule.year_finalised_cost_record', ['year' => $figure->fiscalYear->year, 'canvas' => $canvas]),
                ]);
            }
        }
    }

    /**
     * @param  list<CostRecord>  $records
     * @return array<string, mixed>
     */
    private function remove(Organisation $organisation, array $records): array
    {
        $tables = ['cost_records' => [], 'yearly_figures' => [], 'allocation_sets' => [], 'allocation_lines' => []];

        foreach ($records as $record) {
            $figures = $record->yearlyFigures()->get();
            $sets = $this->sets($record)->get();
            $lines = AllocationLine::whereIn('allocation_set_id', $sets->modelKeys())->get();

            $tables['cost_records'][] = $record->getAttributes();
            $tables['yearly_figures'] = array_merge($tables['yearly_figures'], $this->raw($figures));
            $tables['allocation_sets'] = array_merge($tables['allocation_sets'], $this->raw($sets));
            $tables['allocation_lines'] = array_merge($tables['allocation_lines'], $this->raw($lines));

            foreach ([$lines, $sets, $figures] as $dependents) {
                foreach ($dependents as $dependent) {
                    $dependent->delete();
                }
            }

            $record->delete();
        }

        return ['organisation_id' => $organisation->getKey()] + $tables;
    }

    /** @return Builder<AllocationSet> */
    private function sets(CostRecord $record): Builder
    {
        return AllocationSet::where('cost_record_id', $record->getKey());
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $models
     * @return list<array<string, mixed>>
     */
    private function raw(Collection $models): array
    {
        return $models->map(fn ($model) => $model->getAttributes())->all();
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
