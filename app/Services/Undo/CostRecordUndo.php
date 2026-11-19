<?php

namespace App\Services\Undo;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Application;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\UndoRecord;
use App\Models\YearlyFigure;
use App\Services\Allocation\FinalisedYears;
use App\Services\Audit\AuditContext;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CostRecordUndo
{
    use WritableGuard;

    public const TABLES = [
        'cost_records' => CostRecord::class,
        'yearly_figures' => YearlyFigure::class,
        'allocation_sets' => AllocationSet::class,
        'allocation_lines' => AllocationLine::class,
    ];

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly FinalisedYears $finalisedYears,
        private readonly AuditContext $context,
    ) {}

    public function restore(UndoRecord $record): void
    {
        /** @var array<string, mixed> $stored */
        $stored = (array) $record->payload;
        /** @var array<string, list<array<string, mixed>>> $payload */
        $payload = array_intersect_key($stored, self::TABLES);
        $organisation = Organisation::query()->whereKey($stored['organisation_id'])->first();

        if ($organisation === null) {
            $this->refuse('undo_parent_gone');
        }

        $this->writes->run([$organisation], function () use ($organisation, $payload, $record) {
            $this->assertWritable($organisation);
            $this->assertEveryRuleStillHolds($payload);
            $this->context->during('undone', fn () => $this->insert($payload));

            $record->delete();
        });
    }

    /** @param array<string, list<array<string, mixed>>> $payload */
    private function assertEveryRuleStillHolds(array $payload): void
    {
        $this->assertPresent(Owner::class, array_column($payload['cost_records'], 'owner_id'));
        $this->assertPresent(Application::class, array_filter(array_column($payload['cost_records'], 'application_id')));
        $this->assertPresent(Canvas::class, array_column($payload['allocation_sets'], 'canvas_id'));
        $this->assertPresent(Element::class, array_column($payload['allocation_lines'], 'element_id'));
        $this->assertPresent(CostRecord::class, array_diff(array_column($payload['yearly_figures'], 'cost_record_id'), array_column($payload['cost_records'], 'id')));

        foreach ($payload['cost_records'] as $record) {
            if ($record['code_normalised'] !== null && CostRecord::where('organisation_id', $record['organisation_id'])->where('code_normalised', $record['code_normalised'])->exists()) {
                throw ValidationException::withMessages(['rule' => __('cost_records.code_taken')]);
            }
        }

        foreach ($payload['yearly_figures'] as $figure) {
            if (YearlyFigure::where('cost_record_id', $figure['cost_record_id'])->where('fiscal_year_id', $figure['fiscal_year_id'])->exists()) {
                throw ValidationException::withMessages(['rule' => __('yearly_figures.year_taken')]);
            }
        }

        $years = array_merge(array_column($payload['yearly_figures'], 'fiscal_year_id'), array_column($payload['allocation_sets'], 'fiscal_year_id'));

        foreach ($this->assertPresent(FiscalYear::class, $years) as $year) {
            $this->finalisedYears->assertOpenInOrganisation($year);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, mixed>  $identifiers
     * @return Collection<int, TModel>
     */
    private function assertPresent(string $model, array $identifiers): Collection
    {
        $identifiers = array_values(array_unique($identifiers));
        $present = $model::query()->whereKey($identifiers)->get();

        if ($present->count() !== count($identifiers)) {
            $this->refuse('undo_parent_gone');
        }

        return $present;
    }

    /** @param array<string, list<array<string, mixed>>> $payload */
    private function insert(array $payload): void
    {
        foreach (self::TABLES as $table => $model) {
            foreach ($payload[$table] as $row) {
                (new $model)->forceFill($row)->save();
            }
        }
    }
}
