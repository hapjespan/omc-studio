<?php

namespace App\Services\Exchange;

use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use Closure;
use Illuminate\Database\Eloquent\Collection;

class CanvasImportConflicts
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{errors: list<array{location: string, message: string}>, reused: list<string>}
     */
    public function find(array $data, ?Organisation $target, string $canvasName, ?string $organisationName = null): array
    {
        $errors = [];
        $reused = [];
        $add = function (string $location, string $key, array $replace = []) use (&$errors) {
            $errors[] = ['location' => $location, 'message' => __($key, ['location' => $location, ...$replace])];
        };

        if ($target === null) {
            $organisationName !== null && Organisation::where('name_normalised', Organisation::normalise($organisationName))->exists() && $add('organisation_name', 'jsonx.organisation_name_taken');

            return ['errors' => $errors, 'reused' => $reused];
        }

        Canvas::where('scope_key', 'organisation:'.$target->getKey())->where('name_normalised', Canvas::normalise($canvasName))->exists() && $add('canvas_name', 'jsonx.canvas_name_taken');

        $years = FiscalYear::where('organisation_id', $target->getKey())->pluck('year', 'id')->all();
        $finalised = FinalisedYear::query()
            ->join('canvases', 'canvases.id', '=', 'finalised_years.canvas_id')
            ->whereIn('finalised_years.fiscal_year_id', array_keys($years))
            ->orderByDesc('canvases.id')
            ->pluck('canvases.name', 'finalised_years.fiscal_year_id')
            ->mapWithKeys(fn (string $name, int $yearId) => [$years[$yearId] => $name])->all();
        $existing = CostRecord::with(['owner', 'application', 'yearlyFigures'])->where('organisation_id', $target->getKey())->get();
        $byId = $existing->keyBy('id');
        $byCode = $existing->keyBy('code_normalised');

        foreach ($data['cost_records'] as $position => $record) {
            $at = 'cost_records['.$position.']';
            $current = $byId->get($record['id']);

            if ($current !== null) {
                if ($this->signature($current, $years) === $this->fileSignature($record)) {
                    $reused[] = $record['id'];
                } else {
                    $add($at, 'jsonx.cost_record_differs', ['code' => $record['code'] ?? $record['name'] ?? '']);
                }

                continue;
            }

            $code = ($record['code'] ?? null) === null ? null : mb_strtolower(trim($record['code']));
            $code !== null && $byCode->has($code) && $add($at.'.code', 'jsonx.code_taken', ['code' => $record['code']]);

            foreach ($record['yearly_figures'] as $number => $figure) {
                isset($finalised[$figure['year']]) && $add($at.'.yearly_figures['.$number.'].year', 'jsonx.year_finalised', ['year' => $figure['year'], 'canvas' => $finalised[$figure['year']]]);
            }
        }

        $this->incomplete($data, $existing, $years, $add);

        return ['errors' => $errors, 'reused' => $reused];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CostRecord>  $existing
     * @param  array<int, int>  $years
     */
    private function incomplete(array $data, Collection $existing, array $years, Closure $add): void
    {
        $inFile = array_column($data['cost_records'], 'id', 'id');

        foreach ($data['canvas']['finalised_years'] as $position => $finalised) {
            $missing = $existing
                ->filter(fn (CostRecord $record) => ! isset($inFile[$record->id]) && $record->yearlyFigures->contains(fn (YearlyFigure $figure) => ($years[$figure->fiscal_year_id] ?? null) === $finalised['year']))
                ->map(fn (CostRecord $record) => $record->code ?? $record->name)->take(5)->all();

            $missing !== [] && $add('canvas.finalised_years['.$position.'].year', 'jsonx.finalised_incomplete', ['year' => $finalised['year'], 'records' => implode(', ', $missing)]);
        }
    }

    /**
     * @param  array<int, int>  $years
     * @return array<string, mixed>
     */
    private function signature(CostRecord $record, array $years): array
    {
        return $this->normalised([
            'code' => $record->code,
            'name' => $record->name,
            'cost_kind' => $record->cost_kind->value,
            'supplier' => $record->supplier,
            'owner' => $record->owner->name,
            'cost_centre' => $record->cost_centre,
            'application' => $record->application?->name,
            'description' => $record->description,
            'figures' => $record->yearlyFigures->mapWithKeys(fn (YearlyFigure $figure) => [$years[$figure->fiscal_year_id] => [
                ...$figure->only(['budget_cents', 'forecast_cents', 'run_bp', 'change_bp', 'fte_hundredths']),
                'reference_date' => $figure->reference_date?->format('Y-m-d'),
                'remark' => $figure->remark,
            ]])->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function fileSignature(array $record): array
    {
        $keys = ['budget_cents', 'forecast_cents', 'run_bp', 'change_bp', 'fte_hundredths', 'reference_date', 'remark'];

        return $this->normalised([
            ...array_map(fn (string $key) => $record[$key] ?? null, array_combine($fields = ['code', 'name', 'cost_kind', 'supplier', 'owner', 'cost_centre', 'application', 'description'], $fields)),
            'figures' => array_column(array_map(fn (array $figure) => [$figure['year'], array_map(fn (string $key) => $figure[$key] ?? null, array_combine($keys, $keys))], $record['yearly_figures'] ?? []), 1, 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalised(array $values): array
    {
        ksort($values['figures']);

        foreach ($values['figures'] as &$figure) {
            $figure = array_map(fn ($value) => $value === '' ? null : $value, $figure);
            ksort($figure);
        }

        foreach (['code', 'owner', 'application'] as $key) {
            $values[$key] = $values[$key] === null ? null : mb_strtolower(trim($values[$key]));
        }

        foreach (['supplier', 'cost_centre', 'description'] as $key) {
            $values[$key] = $values[$key] === '' ? null : $values[$key];
        }

        ksort($values);

        return $values;
    }
}
