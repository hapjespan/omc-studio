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

            $figures = $record->yearlyFigures()->get();
            $sets = $this->sets($record)->get();
            $lines = AllocationLine::whereIn('allocation_set_id', $sets->modelKeys())->get();

            $token = $this->undo->remember(UndoStore::COST_RECORD_DELETION, [
                'cost_records' => [$record->getAttributes()],
                'yearly_figures' => $this->raw($figures),
                'allocation_sets' => $this->raw($sets),
                'allocation_lines' => $this->raw($lines),
            ], $user);

            foreach ([$lines, $sets, $figures] as $dependents) {
                foreach ($dependents as $dependent) {
                    $dependent->delete();
                }
            }

            $record->delete();

            return $token;
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
