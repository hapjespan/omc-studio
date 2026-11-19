<?php

namespace App\Services\Exchange;

use App\Enums\CostKind;
use App\Enums\ElementKind;
use App\Enums\StatementKind;
use App\Models\Area;
use App\Models\Modality;
use App\Rules\AmountCents;
use App\Rules\BasisPoints;
use App\Rules\ElementName;
use App\Rules\HexColour;
use App\Rules\IntegerRange;
use App\Rules\TrimmedText;
use App\Services\Structure\ElementFields;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;

class CanvasImportValidator
{
    public const MAX_BYTES = 52_428_800;

    private const MAX_ERRORS = 1000;

    private const DEPTH = 16;

    private const NAME = 150;

    private const TEXT = 2000;

    private const CODE = 50;

    private const POSITION = 1_000_000;

    private const FTE = 999_999;

    private const YEARS = [2000, 2100];

    private const COLOURS = ['fill', 'border_colour', 'text_colour'];

    /** @var list<array{location: string, message: string}> */
    private array $errors = [];

    private int $count = 0;

    /** @var list<int> */
    private array $years = [];

    /** @var array<string, bool> */
    private array $recordIds = [];

    /** @var array<string, string> */
    private array $areaKinds = [];

    /** @var array<string, bool> */
    private array $carriers = [];

    /** @var list<string> */
    private array $importableAreas = [];

    /** @var list<string> */
    private array $modalities = [];

    /** @var list<string> */
    private array $icons = [];

    public function __construct(private readonly ElementFields $fields) {}

    /** @return array{data: ?array<string, mixed>, errors: list<array{location: string, message: string}>, count: int} */
    public function validate(string $content): array
    {
        $this->errors = [];
        $this->count = 0;
        $this->years = [];
        $this->recordIds = [];

        $data = $this->decode($content);

        if ($data === null) {
            return ['data' => null, 'errors' => [['location' => 'file', 'message' => __('jsonx.refused_file')]], 'count' => 1];
        }

        $this->reference();
        $this->organisation($data['organisation'] ?? null);
        $this->costRecords($data['cost_records'] ?? null);
        $this->canvas($data['canvas'] ?? null);

        return ['data' => $data, 'errors' => $this->errors, 'count' => $this->count];
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
        $areas = Area::all();

        $this->areaKinds = $areas->pluck('kind', 'key')->all();
        $this->carriers = $areas->pluck('carries_costs', 'key')->all();
        $this->importableAreas = array_values(array_keys(array_filter($this->areaKinds, fn (string $kind) => $kind !== 'text')));
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
            }

            if ($code !== null) {
                $this->unique(mb_strtolower(trim($code)), $at.'.code', $codes);
            }

            $this->text($record, 'name', $at.'.name', self::NAME, true);
            $this->oneOf($record['cost_kind'] ?? null, $at.'.cost_kind', array_column(CostKind::cases(), 'value'));
            $this->text($record, 'supplier', $at.'.supplier', self::NAME);
            $this->text($record, 'owner', $at.'.owner', self::NAME, true);
            $this->text($record, 'cost_centre', $at.'.cost_centre', self::CODE);
            $this->text($record, 'application', $at.'.application', self::NAME);
            $this->text($record, 'description', $at.'.description', self::TEXT);
            $this->date($record['archived_at'] ?? null, $at.'.archived_at');
            $this->figures($record['yearly_figures'] ?? null, $at);
        }
    }

    private function figures(mixed $node, string $parent): void
    {
        $seen = [];

        foreach ($this->listOf($node, $parent.'.yearly_figures') as $index => $figure) {
            $at = $parent.'.yearly_figures['.$index.']';
            $figure = $this->object($figure, $at);

            $this->year($figure['year'] ?? null, $at.'.year', $seen);
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
        $this->layout($canvas['layout'] ?? null);
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

            if (! array_key_exists('body', $statement)) {
                $this->fail($at.'.body', 'jsonx.required');
            }

            $this->text($statement, 'title', $at.'.title', self::NAME);
            $this->text($statement, 'body', $at.'.body', self::TEXT);
            $this->integer($statement, 'position', $at.'.position', 0, self::POSITION, true);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function elements(mixed $node): array
    {
        $index = [];
        $ids = [];
        $names = [];

        foreach ($this->listOf($node, 'canvas.elements') as $position => $element) {
            $at = 'canvas.elements['.$position.']';
            $element = $this->object($element, $at);

            $id = $this->value($element['id'] ?? null, $at.'.id', self::NAME, true);
            $area = $this->oneOf($element['area'] ?? null, $at.'.area', $this->importableAreas);
            $areaKind = $area === null ? null : $this->areaKinds[$area];
            $kind = $this->oneOf($element['kind'] ?? null, $at.'.kind', array_column(ElementKind::cases(), 'value'));
            $name = $this->text($element, 'name', $at.'.name', self::NAME, true, true);
            $this->text($element, 'description', $at.'.description', self::TEXT);
            $modality = $this->modality($element['modality'] ?? null, $at.'.modality', $areaKind);

            if ($this->text($element, 'rationale', $at.'.rationale', self::TEXT) !== null && $modality === null) {
                $this->fail($at.'.rationale', 'jsonx.rationale_without_modality');
            }

            $this->icon($element['icon'] ?? null, $at.'.icon', $areaKind);
            $this->integer($element, 'position', $at.'.position', 0, self::POSITION, true);

            $unknown = $element['is_unknown_allocation'] ?? null;

            if (! is_bool($unknown)) {
                $this->fail($at.'.is_unknown_allocation', 'jsonx.not_boolean');
            }

            $archived = $element['archived_at'] ?? null;
            $this->date($archived, $at.'.archived_at');

            $parent = $element['parent_id'] ?? null;

            if ($parent !== null && ! is_string($parent)) {
                $this->fail($at.'.parent_id', 'jsonx.not_text');

                $parent = null;
            }

            if ($name !== null && $area !== null && $archived === null && $unknown !== true) {
                $this->unique([$area, $parent, mb_strtolower(trim($name))], $at.'.name', $names, 'jsonx.name_taken');
            }

            if ($id === null || ! $this->unique($id, $at.'.id', $ids)) {
                continue;
            }

            $index[$id] = [
                'at' => $at,
                'area' => $area,
                'kind' => $kind,
                'parent' => $parent,
                'modality' => $modality,
                'archived' => $archived !== null,
                'unknown' => $unknown === true,
            ];
        }

        return $index;
    }

    private function modality(mixed $value, string $location, ?string $areaKind): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! in_array($value, $this->modalities, true)) {
            $this->fail($location, 'jsonx.unknown_value');

            return null;
        }

        if ($areaKind !== 'activities') {
            $this->fail($location, 'jsonx.modality_not_allowed');
        }

        return $value;
    }

    private function icon(mixed $value, string $location, ?string $areaKind): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || ! in_array($value, $this->icons, true)) {
            $this->fail($location, 'jsonx.unknown_value');

            return;
        }

        if ($areaKind !== 'list') {
            $this->fail($location, 'jsonx.icon_not_allowed');
        }
    }

    /** @param array<string, array<string, mixed>> $index */
    private function structure(array $index): void
    {
        foreach ($index as $element) {
            $at = $element['at'].'.parent_id';
            $kind = $element['kind'];

            if ($kind === null) {
                continue;
            }

            if ($kind === ElementKind::Domain->value) {
                if ($element['parent'] !== null) {
                    $this->structureFail($at, 'domain_parent');
                }

                continue;
            }

            $cluster = $kind === ElementKind::Cluster->value;
            $reason = $cluster ? 'cluster_parent' : 'activity_parent';

            if ($cluster && $element['area'] !== null && $this->areaKinds[$element['area']] !== 'activities') {
                $this->structureFail($at, 'cluster_area');

                continue;
            }

            $parent = $element['parent'] === null ? null : ($index[$element['parent']] ?? null);

            if ($element['parent'] !== null && $parent === null) {
                $this->fail($at, 'jsonx.unknown_reference');

                continue;
            }

            if ($parent === null || $parent['area'] !== $element['area'] || ! $this->parentFits($cluster, $parent['kind'])) {
                $this->structureFail($at, $reason);
            }
        }
    }

    private function parentFits(bool $cluster, ?string $parentKind): bool
    {
        return $cluster ? $parentKind === ElementKind::Domain->value : $parentKind === ElementKind::Domain->value || $parentKind === ElementKind::Cluster->value;
    }

    private function structureFail(string $location, string $reason): void
    {
        $this->fail($location, 'jsonx.structure', ['reason' => __('jsonx.reason_'.$reason)]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @return array<string, bool>
     */
    private function sets(mixed $node, array $index): array
    {
        $targeted = [];
        $seen = [];

        foreach ($this->listOf($node, 'canvas.allocation_sets') as $position => $set) {
            $at = 'canvas.allocation_sets['.$position.']';
            $set = $this->object($set, $at);
            $recordId = $set['cost_record_id'] ?? null;
            $year = $set['year'] ?? null;

            if (! is_string($recordId) || ! isset($this->recordIds[$recordId])) {
                $this->fail($at.'.cost_record_id', 'jsonx.unknown_reference');
            }

            if (! in_array($year, $this->years, true)) {
                $this->fail($at.'.year', 'jsonx.year_unknown');
            }

            $this->unique([$recordId, $year], $at, $seen);

            $lines = $this->listOf($set['lines'] ?? null, $at.'.lines');

            if ($lines === []) {
                ($set['lines'] ?? null) === [] && $this->fail($at.'.lines', 'jsonx.required');

                continue;
            }

            $sum = 0;
            $sound = true;
            $elements = [];

            foreach ($lines as $number => $line) {
                $location = $at.'.lines['.$number.']';
                $line = $this->object($line, $location);
                $elementId = $line['element_id'] ?? null;
                $target = is_string($elementId) ? ($index[$elementId] ?? null) : null;

                if ($target === null) {
                    $this->fail($location.'.element_id', 'jsonx.unknown_reference');

                    $sound = false;
                } elseif (! $this->carries($target)) {
                    $this->fail($location.'.element_id', 'jsonx.target_invalid');

                    $sound = false;
                } elseif (! $this->unique($elementId, $location.'.element_id', $elements)) {
                    $sound = false;
                } else {
                    $targeted[$elementId] = true;
                }

                $points = $this->integer($line, 'basis_points', $location.'.basis_points', 1, BasisPoints::MAX, true);

                if ($points === null) {
                    $sound = false;

                    continue;
                }

                $sum += $points;
            }

            if ($sound && $sum !== BasisPoints::MAX) {
                $this->fail($at, 'jsonx.hundred_percent');
            }
        }

        return $targeted;
    }

    /** @param array<string, mixed> $target */
    private function carries(array $target): bool
    {
        return ! $target['archived'] && ! $target['unknown'] && $target['area'] !== null && ($this->carriers[$target['area']] ?? false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, bool>  $targeted
     */
    private function unknownAllocation(array $index, array $targeted): void
    {
        $seen = false;

        foreach ($index as $id => $element) {
            if (! $element['unknown']) {
                continue;
            }

            if ($seen) {
                $this->fail($element['at'], 'jsonx.unknown_repeated');

                continue;
            }

            $seen = true;

            $sound = $element['kind'] === ElementKind::Domain->value
                && $element['area'] === 'supporting_activities'
                && $element['parent'] === null
                && $element['modality'] === null
                && ! isset($targeted[$id])
                && ! $this->hasChildren($index, $id);

            if (! $sound) {
                $this->fail($element['at'], 'jsonx.unknown_invalid');
            }
        }
    }

    /** @param array<string, array<string, mixed>> $index */
    private function hasChildren(array $index, string $id): bool
    {
        foreach ($index as $element) {
            if ($element['parent'] === $id) {
                return true;
            }
        }

        return false;
    }

    private function finalisedYears(mixed $node): void
    {
        $seen = [];

        foreach ($this->listOf($node, 'canvas.finalised_years') as $position => $finalised) {
            $at = 'canvas.finalised_years['.$position.']';
            $finalised = $this->object($finalised, $at);

            $this->year($finalised['year'] ?? null, $at.'.year', $seen);
            $this->date($finalised['finalised_at'] ?? null, $at.'.finalised_at', null, true);
        }
    }

    private function layout(mixed $node): void
    {
        if ($node === null) {
            return;
        }

        if (! is_array($node)) {
            $this->fail('canvas.layout', 'jsonx.not_object');

            return;
        }

        $this->colours($node, 'canvas.layout');
    }

    /** @param array<mixed, mixed> $node */
    private function colours(array $node, string $parent): void
    {
        foreach ($node as $key => $value) {
            $location = is_int($key) ? $parent.'['.$key.']' : $parent.'.'.$key;

            if (is_array($value)) {
                $this->colours($value, $location);

                continue;
            }

            if ($value !== null && is_string($key) && in_array($key, self::COLOURS, true)) {
                $this->rule(new HexColour($location), $location, $value);
            }
        }
    }

    /** @param array<string, bool> $seen */
    private function year(mixed $value, string $location, array &$seen): void
    {
        if (! in_array($value, $this->years, true)) {
            $this->fail($location, 'jsonx.year_unknown');

            return;
        }

        $this->unique($value, $location, $seen);
    }

    /** @param array<mixed, mixed> $node */
    private function text(array $node, string $key, string $location, int $max, bool $required = false, bool $isName = false): ?string
    {
        return $this->value($node[$key] ?? null, $location, $max, $required, $isName);
    }

    private function value(mixed $value, string $location, int $max, bool $required = false, bool $isName = false): ?string
    {
        if ($value === null || $value === '') {
            if ($required) {
                $this->fail($location, 'jsonx.required');
            }

            return null;
        }

        if (! is_string($value)) {
            $this->fail($location, 'jsonx.not_text');

            return null;
        }

        $this->rule($isName ? new ElementName($location) : new TrimmedText($location, $max), $location, $value);

        return $value;
    }

    /** @param array<mixed, mixed> $node */
    private function integer(array $node, string $key, string $location, int $min, int $max, bool $required = false): ?int
    {
        $value = $node[$key] ?? null;

        if ($value === null) {
            if ($required) {
                $this->fail($location, 'jsonx.required');
            }

            return null;
        }

        $this->rule(new IntegerRange($location, $min, $max), $location, $value);

        return is_int($value) ? $value : null;
    }

    /** @param list<string> $allowed */
    private function oneOf(mixed $value, string $location, array $allowed): ?string
    {
        if (is_string($value) && in_array($value, $allowed, true)) {
            return $value;
        }

        $this->fail($location, 'jsonx.unknown_value');

        return null;
    }

    private function date(mixed $value, string $location, ?string $format = null, bool $required = false): void
    {
        if ($value === null) {
            if ($required) {
                $this->fail($location, 'jsonx.required');
            }

            return;
        }

        $sound = is_string($value) && $value !== '' && ($format === null
            ? rescue(fn () => Carbon::parse($value) !== null, false, false)
            : $this->matches($value, $format));

        if (! $sound) {
            $this->fail($location, 'jsonx.date_invalid');
        }
    }

    private function matches(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    /** @return array<mixed, mixed> */
    private function object(mixed $value, string $location): array
    {
        if (is_array($value) && ($value === [] || ! array_is_list($value))) {
            return $value;
        }

        $this->fail($location, 'jsonx.not_object');

        return [];
    }

    /** @return list<mixed> */
    private function listOf(mixed $value, string $location): array
    {
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        $this->fail($location, 'jsonx.not_list');

        return [];
    }

    /** @param array<string, bool> $seen */
    private function unique(mixed $value, string $location, array &$seen, string $key = 'jsonx.duplicate'): bool
    {
        $hash = is_array($value) ? serialize($value) : gettype($value).':'.$value;

        if (isset($seen[$hash])) {
            $this->fail($location, $key);

            return false;
        }

        $seen[$hash] = true;

        return true;
    }

    private function rule(ValidationRule $rule, string $location, mixed $value): void
    {
        $rule->validate($location, $value, fn (string $message) => $this->record($location, $message));
    }

    /** @param array<string, string> $replace */
    private function fail(string $location, string $key, array $replace = []): void
    {
        $this->record($location, __($key, ['location' => $location, ...$replace]));
    }

    private function record(string $location, string $message): void
    {
        $this->count++;

        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = ['location' => $location, 'message' => $message];
        }
    }
}
