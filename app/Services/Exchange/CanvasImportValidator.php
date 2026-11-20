<?php

namespace App\Services\Exchange;

use App\Enums\CostKind;
use App\Enums\StatementKind;
use App\Models\Area;
use App\Models\Modality;
use App\Models\Organisation;
use App\Rules\AmountCents;
use App\Rules\BasisPoints;
use App\Rules\IntegerRange;
use App\Services\Exchange\Concerns\ChecksFields;
use App\Services\Exchange\Concerns\ChecksStructure;
use App\Services\Layout\LayoutValidator;
use App\Services\Structure\ElementFields;
use JsonException;

class CanvasImportValidator
{
    use ChecksFields, ChecksStructure;

    public const MAX_BYTES = 52_428_800;

    private const DEPTH = 16;

    private const NAME = 150;

    private const TEXT = 2000;

    private const CODE = 50;

    private const POSITION = 1_000_000;

    private const FTE = 999_999;

    private const YEARS = [2000, 2100];

    public function __construct(private readonly ElementFields $fields, private readonly CanvasImportConflicts $conflicts) {}

    /** @return array{data: ?array<string, mixed>, errors: list<array{location: string, message: string}>, count: int} */
    public function validate(string $content): array
    {
        $data = $this->decode($content);

        if ($data === null) {
            return ['data' => null, 'errors' => [['location' => 'file', 'message' => __('jsonx.refused_file')]], 'count' => 1];
        }

        $foundation = $this->foundation($data);
        $block = $this->block($data['canvas'] ?? null);

        return [
            'data' => [...$foundation['data'], 'canvas' => $block['canvas']],
            'errors' => [...$foundation['errors'], ...$block['errors']],
            'count' => $foundation['count'] + $block['count'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, errors: list<array{location: string, message: string}>, count: int}
     */
    public function foundation(array $data): array
    {
        $this->errors = [];
        $this->count = 0;
        $this->years = [];
        $this->recordIds = [];
        $this->recordYears = [];
        $this->recordLabels = [];

        $data = CanvasImportShape::apply($data);

        $this->reference();
        $this->organisation($data['organisation'] ?? null);
        $this->costRecords($data['cost_records'] ?? null);

        return ['data' => $data, 'errors' => $this->errors, 'count' => $this->count];
    }

    /** @return array{canvas: mixed, errors: list<array{location: string, message: string}>, count: int} */
    public function blockWithoutRecords(mixed $node): array
    {
        $this->years = [];
        $this->recordIds = [];
        $this->recordYears = [];
        $this->recordLabels = [];

        return $this->block($node);
    }

    /** @return array{canvas: mixed, errors: list<array{location: string, message: string}>, count: int} */
    public function block(mixed $node): array
    {
        $this->errors = [];
        $this->count = 0;
        $this->setYears = [];

        $this->reference();

        $node = CanvasImportShape::canvas($node);
        $this->canvas($node);

        return ['canvas' => $node, 'errors' => $this->errors, 'count' => $this->count];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{errors: list<array{location: string, message: string}>, reused: list<string>}
     */
    public function conflicts(array $data, ?Organisation $target, string $canvasName, ?string $organisationName = null): array
    {
        return $this->conflicts->find($data, $target, $canvasName, $organisationName);
    }

    /**
     * @return array{data: ?array<string, mixed>, errors: list<array{location: string, message: string}>, count: int, reused: list<string>}
     */
    public function check(string $content, ?Organisation $target, string $canvasName, ?string $organisationName = null): array
    {
        $result = $this->validate($content);
        $reused = [];

        if ($result['data'] !== null && $result['count'] === 0) {
            $found = $this->conflicts($result['data'], $target, $canvasName, $organisationName);
            $reused = $found['reused'];

            foreach ($found['errors'] as $error) {
                $this->count++;
                $result['count']++;
                count($result['errors']) < self::MAX_ERRORS && $result['errors'][] = $error;
            }
        }

        return [...$result, 'reused' => $reused];
    }

    /** @return ?array<string, mixed> */
    private function decode(string $content): ?array
    {
        if (strlen($content) > self::MAX_BYTES) {
            return null;
        }

        try {
            $data = json_decode($content, true, self::DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || array_is_list($data)) {
            return null;
        }

        if (($data['format'] ?? null) !== CanvasExport::FORMAT || ($data['format_version'] ?? null) !== 1) {
            return null;
        }

        return $data;
    }

    private function reference(): void
    {
        if ($this->areaKinds !== []) {
            return;
        }

        $areas = Area::all();

        $this->areaKinds = $areas->pluck('kind', 'key')->all();
        $this->carriers = $areas->pluck('carries_costs', 'key')->all();
        $this->importableAreas = array_keys(array_filter($this->areaKinds, fn (string $kind) => $kind !== 'text'));
        $this->modalities = Modality::pluck('key')->all();
        $this->icons = $this->fields->icons();
    }

    private function organisation(mixed $node): void
    {
        $organisation = $this->object($node, 'organisation');

        $this->text($organisation, 'name', 'organisation.name', self::NAME, true);

        foreach ($this->listOf($organisation['owners'] ?? null, 'organisation.owners') as $index => $owner) {
            $this->value($owner, 'organisation.owners['.$index.']', self::NAME, true);
        }

        foreach ($this->listOf($organisation['applications'] ?? null, 'organisation.applications') as $index => $application) {
            $at = 'organisation.applications['.$index.']';
            $application = $this->object($application, $at);

            $this->text($application, 'name', $at.'.name', self::NAME, true);
            $this->text($application, 'supplier', $at.'.supplier', self::NAME);
        }

        $seen = [];

        foreach ($this->listOf($organisation['fiscal_years'] ?? null, 'organisation.fiscal_years') as $index => $year) {
            $at = 'organisation.fiscal_years['.$index.']';
            $before = $this->count;

            $this->rule(new IntegerRange($at, self::YEARS[0], self::YEARS[1]), $at, $year);

            if ($this->count === $before && $this->unique($year, $at, $seen)) {
                $this->years[] = $year;
            }
        }
    }

    private function costRecords(mixed $node): void
    {
        $ids = [];
        $codes = [];

        foreach ($this->listOf($node, 'cost_records') as $index => $record) {
            $at = 'cost_records['.$index.']';
            $record = $this->object($record, $at);

            $id = $this->value($record['id'] ?? null, $at.'.id', self::NAME, true);
            $code = $this->text($record, 'code', $at.'.code', self::CODE);

            if ($id !== null && $this->unique($id, $at.'.id', $ids)) {
                $this->recordIds[$id] = true;
                $this->recordYears[$id] = [];
            }

            if ($code !== null) {
                $this->unique(mb_strtolower(trim($code)), $at.'.code', $codes);
            }

            $name = $this->text($record, 'name', $at.'.name', self::NAME, true);
            $id !== null && $this->recordLabels[$id] = $code ?? $name ?? $id;
            $this->oneOf($record['cost_kind'] ?? null, $at.'.cost_kind', array_column(CostKind::cases(), 'value'));
            $this->text($record, 'supplier', $at.'.supplier', self::NAME);
            $this->text($record, 'owner', $at.'.owner', self::NAME, true);
            $this->text($record, 'cost_centre', $at.'.cost_centre', self::CODE);
            $this->text($record, 'application', $at.'.application', self::NAME);
            $this->text($record, 'description', $at.'.description', self::TEXT);
            $this->date($record['archived_at'] ?? null, $at.'.archived_at');
            $this->figures($record['yearly_figures'] ?? null, $at, $id);
        }
    }

    private function figures(mixed $node, string $parent, ?string $recordId): void
    {
        $seen = [];

        foreach ($this->listOf($node, $parent.'.yearly_figures') as $index => $figure) {
            $at = $parent.'.yearly_figures['.$index.']';
            $figure = $this->object($figure, $at);

            $this->year($figure['year'] ?? null, $at.'.year', $seen);

            if ($recordId !== null && is_int($figure['year'] ?? null)) {
                $this->recordYears[$recordId][$figure['year']] = true;
            }
            $this->integer($figure, 'budget_cents', $at.'.budget_cents', 0, AmountCents::MAX_CENTS, true);
            $this->integer($figure, 'forecast_cents', $at.'.forecast_cents', 0, AmountCents::MAX_CENTS);

            $run = $this->integer($figure, 'run_bp', $at.'.run_bp', 0, BasisPoints::MAX);
            $change = $this->integer($figure, 'change_bp', $at.'.change_bp', 0, BasisPoints::MAX);

            if ($run !== null && $change !== null && $run + $change > BasisPoints::MAX) {
                $this->fail($at.'.change_bp', 'jsonx.split');
            }

            $this->integer($figure, 'fte_hundredths', $at.'.fte_hundredths', 0, self::FTE);
            $this->date($figure['reference_date'] ?? null, $at.'.reference_date', 'Y-m-d');
            $this->text($figure, 'remark', $at.'.remark', self::TEXT);
        }
    }

    private function canvas(mixed $node): void
    {
        $canvas = $this->object($node, 'canvas');

        $this->text($canvas, 'name', 'canvas.name', self::NAME, true, true);
        $this->statements($canvas['statements'] ?? null);

        $elements = $this->elements($canvas['elements'] ?? null);

        $this->structure($elements);
        $this->unknownAllocation($elements, $this->sets($canvas['allocation_sets'] ?? null, $elements));
        $this->finalisedYears($canvas['finalised_years'] ?? null);
        $this->layout($canvas['layout'] ?? null, $elements);
    }

    private function statements(mixed $node): void
    {
        $once = [];

        foreach ($this->listOf($node, 'canvas.statements') as $index => $statement) {
            $at = 'canvas.statements['.$index.']';
            $statement = $this->object($statement, $at);
            $kind = $this->oneOf($statement['kind'] ?? null, $at.'.kind', array_column(StatementKind::cases(), 'value'));

            if ($kind === StatementKind::Mission->value || $kind === StatementKind::Vision->value) {
                $this->unique($kind, $at, $once, 'jsonx.statement_repeated');
            }

            $this->text($statement, 'title', $at.'.title', self::NAME);
            $this->text($statement, 'body', $at.'.body', self::TEXT, true);
            $this->integer($statement, 'position', $at.'.position', 0, self::POSITION, true);
        }
    }

    private function finalisedYears(mixed $node): void
    {
        $seen = [];

        foreach ($this->listOf($node, 'canvas.finalised_years') as $position => $finalised) {
            $at = 'canvas.finalised_years['.$position.']';
            $finalised = $this->object($finalised, $at);

            $this->year($finalised['year'] ?? null, $at.'.year', $seen);
            $this->complete($finalised['year'] ?? null, $at.'.year');
            $this->date($finalised['finalised_at'] ?? null, $at.'.finalised_at', null, true);
        }
    }

    private function complete(mixed $year, string $location): void
    {
        if (! is_int($year) || ! in_array($year, $this->years, true)) {
            return;
        }

        $missing = [];

        foreach ($this->recordYears as $id => $years) {
            isset($years[$year]) && ! isset($this->setYears[$id][$year]) && $missing[] = $this->recordLabels[$id] ?? $id;
        }

        $missing !== [] && $this->fail($location, 'jsonx.finalised_incomplete', ['year' => $year, 'records' => implode(', ', array_slice($missing, 0, 5))]);
    }

    /** @param array<string, array<string, mixed>> $elements */
    private function layout(mixed $node, array $elements): void
    {
        if ($node === null) {
            return;
        }

        if (! is_array($node) || array_is_list($node) && $node !== []) {
            $this->fail('canvas.layout', 'jsonx.not_object');

            return;
        }

        foreach (['areas', 'elements'] as $key) {
            if (! is_array($node[$key] ?? null)) {
                $this->fail('canvas.layout.'.$key, 'jsonx.not_object');

                return;
            }
        }

        foreach (array_diff(array_keys($node), ['areas', 'elements']) as $key) {
            $this->fail('canvas.layout.'.$key, 'jsonx.unknown_value');
        }

        $active = [];

        foreach ($elements as $id => $element) {
            if (! $element['archived'] && $element['area'] !== null && $element['kind'] !== null && $element['name'] !== null) {
                $active[] = ['id' => (string) $id, 'area' => $element['area'], 'parent' => $element['parent'], 'kind' => $element['kind'], 'name' => $element['name']];
            }
        }

        foreach ((new LayoutValidator($active))->refusals($node) as $refusal) {
            $at = $refusal['element'] !== null ? 'canvas.layout.elements.'.$refusal['element'] : 'canvas.layout.areas';
            $this->record($refusal['rule'] === 'style' && $refusal['element'] !== null ? $at.'.style' : $at, $refusal['message']);
        }
    }
}
