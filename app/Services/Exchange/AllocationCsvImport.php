<?php

namespace App\Services\Exchange;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\YearlyFigure;
use App\Rules\BasisPoints;
use App\Services\Allocation\AllocationSets;
use App\Services\Allocation\FinalisedYears;
use App\Services\Audit\AuditContext;
use App\Services\Exchange\Csv\CsvReader;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AllocationCsvImport
{
    use WritableGuard;

    public function __construct(
        private readonly AllocationSets $sets,
        private readonly FinalisedYears $finalised,
        private readonly SerialisedWrite $write,
        private readonly AuditContext $context,
    ) {}

    /**
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>}
     */
    public function preview(Canvas $canvas, string $path): array
    {
        $scan = $this->scan($canvas, $path);

        return ['lines' => $scan['lines'], 'totals' => $scan['totals']];
    }

    /**
     * @return array{totals: array<string, int>}
     */
    public function commit(Canvas $canvas, string $path): array
    {
        $canvas->loadMissing('organisation');

        return $this->write->run(array_filter([$canvas->organisation, $canvas]), fn () => $this->context->during('imported', function () use ($canvas, $path) {
            $scan = $this->scan($canvas, $path);

            if ($scan['totals']['errors'] > 0) {
                throw ValidationException::withMessages(['file' => (string) __('csvx.refused_errors')]);
            }

            foreach ($scan['groups'] as $group) {
                $group['status'] === 'unchanged' || $this->sets->replace($canvas, $group['record'], $group['year'], $group['lines']);
            }

            return ['totals' => $scan['totals']];
        }));
    }

    /**
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>, groups: array<string, array{first: int, record: ?CostRecord, year: ?FiscalYear, lines: list<array{element_id: string, basis_points: int}>, elements: list<Element>, failed: bool, messages: list<string>, status: string}>}
     */
    private function scan(Canvas $canvas, string $path): array
    {
        $canvas->loadMissing('organisation');

        $records = CostRecord::where('organisation_id', $canvas->organisation_id)->whereNotNull('code_normalised')->get()->keyBy('code_normalised');
        $years = FiscalYear::where('organisation_id', $canvas->organisation_id)->get()->keyBy('year');
        $targets = (new TargetPaths($canvas))->lookup();
        $figures = array_flip(YearlyFigure::whereIn('cost_record_id', $records->modelKeys())->get()->map(fn (YearlyFigure $figure) => $figure->cost_record_id.'|'.$figure->fiscal_year_id)->all());

        $rows = [];
        $groups = [];

        foreach ((new CsvReader($path))->lines() as $number => $cells) {
            if ($number === 1) {
                continue;
            }

            if (count($cells) > count(AllocationCsv::COLUMNS)) {
                $rows[$number] = ['key' => null, 'messages' => [(string) __('csvx.too_many_cells', ['line' => $number, 'count' => count($cells), 'expected' => count(AllocationCsv::COLUMNS)])]];

                continue;
            }

            $row = array_combine(AllocationCsv::COLUMNS, array_pad(array_map('trim', $cells), count(AllocationCsv::COLUMNS), ''));
            $record = $records->get(mb_strtolower($row['kostenpost_code']));
            $year = ctype_digit($row['jaar']) ? $years->get((int) $row['jaar']) : null;
            $element = $targets[mb_strtolower($row['doel_pad'])] ?? null;
            $points = BasisPoints::parse($row['percentage']);
            $messages = [];

            $record instanceof CostRecord || $messages[] = (string) __('csvx.code_unknown', ['line' => $number, 'value' => $row['kostenpost_code']]);
            $year instanceof FiscalYear || $messages[] = (string) __('import.year_invalid', ['line' => $number, 'value' => $row['jaar']]);

            if (! $element instanceof Element) {
                $messages[] = (string) __('csvx.path_unknown', ['line' => $number, 'value' => $row['doel_pad']]);
            } elseif (! $element->carriesCosts($canvas)) {
                $messages[] = (string) __('csvx.path_not_target', ['line' => $number, 'value' => $row['doel_pad']]);
            }

            if ($points === null || $points < 1) {
                $messages[] = (string) __('csvx.percentage_invalid', ['line' => $number, 'value' => $row['percentage']]);
            }

            $key = ($record?->getKey() ?? 'code:'.mb_strtolower($row['kostenpost_code'])).'|'.($year?->getKey() ?? 'year:'.$row['jaar']);
            $groups[$key] ??= ['first' => $number, 'record' => $record, 'year' => $year, 'lines' => [], 'elements' => [], 'failed' => false, 'messages' => [], 'status' => 'error'];
            $rows[$number] = ['key' => $key, 'messages' => $messages];

            if ($messages === [] && $element instanceof Element) {
                $groups[$key]['lines'][] = ['element_id' => (string) $element->getKey(), 'basis_points' => $points];
                $groups[$key]['elements'][] = $element;

                continue;
            }

            $groups[$key]['failed'] = true;
        }

        foreach ($groups as $key => $group) {
            if ($group['failed'] || ! $group['record'] instanceof CostRecord || ! $group['year'] instanceof FiscalYear) {
                continue;
            }

            $groups[$key]['messages'] = $this->check($canvas, $group, $figures);
            $groups[$key]['status'] = $this->status($canvas, $group);
        }

        return [...$this->report($rows, $groups), 'groups' => $groups];
    }

    /**
     * @param  array<int, array{key: ?string, messages: list<string>}>  $rows
     * @param  array<string, array<string, mixed>>  $groups
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>}
     */
    private function report(array $rows, array $groups): array
    {
        $lines = [];
        $totals = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];

        foreach ($rows as $number => $row) {
            $group = $row['key'] === null ? null : $groups[$row['key']];
            $messages = [...$row['messages'], ...($group !== null && $number === $group['first'] ? $group['messages'] : [])];
            $status = $messages !== [] || ($group !== null && ($group['failed'] || $group['messages'] !== [])) ? 'error' : $group['status'];

            $totals[$status === 'error' ? 'errors' : $status]++;
            $lines[] = ['line' => $number, 'status' => $status, 'messages' => $messages];
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    /**
     * @param  array{record: CostRecord, year: FiscalYear, lines: list<array{element_id: string, basis_points: int}>, elements: list<Element>}  $group
     * @param  array<string, int>  $figures
     * @return list<string>
     */
    private function check(Canvas $canvas, array $group, array $figures): array
    {
        return array_values(array_filter([
            $this->refusal(fn () => $this->assertWritable($canvas)),
            $this->refusal(fn () => $this->finalised->assertOpenInCanvas($canvas, $group['year'])),
            isset($figures[$group['record']->getKey().'|'.$group['year']->getKey()]) ? null : (string) __('allocation.no_figures', ['year' => $group['year']->year]),
            $this->repeated($group['elements']),
            $this->sum($group['lines']),
        ]));
    }

    private function refusal(Closure $check): ?string
    {
        try {
            $check();

            return null;
        } catch (ValidationException $exception) {
            return (string) Arr::first(Arr::flatten($exception->errors()));
        }
    }

    /**
     * @param  list<Element>  $elements
     */
    private function repeated(array $elements): ?string
    {
        $seen = [];

        foreach ($elements as $element) {
            $key = (string) $element->getKey();

            if (isset($seen[$key])) {
                return (string) __('allocation.target_repeated', ['name' => (string) $element->name]);
            }

            $seen[$key] = true;
        }

        return null;
    }

    /**
     * @param  list<array{element_id: string, basis_points: int}>  $lines
     */
    private function sum(array $lines): ?string
    {
        $total = (int) array_sum(array_column($lines, 'basis_points'));

        return $total === BasisPoints::MAX ? null : (string) __('allocation.sum_invalid', ['total' => sprintf('%d,%02d%%', intdiv($total, 100), $total % 100)]);
    }

    /**
     * @param  array{record: CostRecord, year: FiscalYear, lines: list<array{element_id: string, basis_points: int}>}  $group
     */
    private function status(Canvas $canvas, array $group): string
    {
        $set = AllocationSet::where('canvas_id', $canvas->getKey())
            ->where('cost_record_id', $group['record']->getKey())
            ->where('fiscal_year_id', $group['year']->getKey())
            ->first();

        if (! $set instanceof AllocationSet) {
            return 'new';
        }

        $current = $set->lines()->get()->map(fn (AllocationLine $line) => ['element_id' => (string) $line->element_id, 'basis_points' => $line->basis_points])->all();

        return $current === $group['lines'] ? 'unchanged' : 'updated';
    }
}
