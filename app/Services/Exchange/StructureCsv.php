<?php

namespace App\Services\Exchange;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Services\Audit\AuditContext;
use App\Services\Exchange\Csv\CsvReader;
use App\Services\Structure\ElementTree;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use App\Support\FieldLimits;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class StructureCsv
{
    use WritableGuard;

    public const COLUMNS = ['area', 'domain', 'cluster', 'activity', 'description', 'modality'];

    private const NAME_MAX = FieldLimits::NAME;

    private const DESCRIPTION_MAX = FieldLimits::TEXT;

    /** @var array<string, Area> */
    private array $areas = [];

    /** @var array<string, Modality> */
    private array $modalities = [];

    /** @var array<string, array{element: Element, key: string}> */
    private array $nodes = [];

    public function __construct(
        private readonly ElementTree $tree,
        private readonly SerialisedWrite $write,
        private readonly AuditContext $context,
    ) {}

    /**
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>}
     */
    public function preview(Canvas $canvas, string $path): array
    {
        return $this->scan($canvas, $path, false);
    }

    /**
     * @return array{totals: array<string, int>}
     */
    public function commit(Canvas $canvas, string $path): array
    {
        $canvas->loadMissing('organisation');

        return $this->write->run(array_filter([$canvas->organisation, $canvas]), fn () => $this->context->during('imported', function () use ($canvas, $path) {
            $report = $this->scan($canvas, $path, true);

            if ($report['totals']['errors'] > 0) {
                throw ValidationException::withMessages(['file' => (string) __('csvx.refused_errors')]);
            }

            return ['totals' => $report['totals']];
        }));
    }

    /**
     * @return array{lines: list<array{line: int, status: string, messages: list<string>}>, totals: array<string, int>}
     */
    private function scan(Canvas $canvas, string $path, bool $apply): array
    {
        $this->areas = $this->areaLookup();
        $this->modalities = $this->modalityLookup();
        $this->nodes = $this->nodeLookup($canvas);

        $lines = [];
        $totals = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];
        $blocked = $this->blocked($canvas);

        foreach ((new CsvReader($path))->lines() as $number => $cells) {
            if ($number === 1) {
                continue;
            }

            if ($blocked !== null) {
                $line = ['status' => 'error', 'messages' => [$blocked]];
            } elseif (count($cells) > count(self::COLUMNS)) {
                $line = $this->refusal((string) __('csvx.too_many_cells', ['line' => $number, 'count' => count($cells), 'expected' => count(self::COLUMNS)]));
            } else {
                $line = $this->line($canvas, $number, array_combine(self::COLUMNS, array_pad(array_map('trim', $cells), count(self::COLUMNS), '')), $apply);
            }

            $totals[$line['status'] === 'error' ? 'errors' : $line['status']]++;
            $lines[] = ['line' => $number, 'status' => $line['status'], 'messages' => $line['messages']];
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    private function blocked(Canvas $canvas): ?string
    {
        $canvas->loadMissing('organisation');

        try {
            $this->assertStructureWritable($canvas);

            return null;
        } catch (ValidationException $exception) {
            return (string) Arr::first(Arr::flatten($exception->errors()));
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array{status: string, messages: list<string>}
     */
    private function line(Canvas $canvas, int $number, array $row, bool $apply): array
    {
        $area = $this->areas[mb_strtolower($row['area'])] ?? null;

        if (! $area instanceof Area) {
            return $this->refusal((string) __('csvx.area_unknown', ['line' => $number, 'value' => $row['area']]));
        }

        if ($area->kind === 'text') {
            return $this->refusal((string) __('csvx.area_no_elements', ['line' => $number]));
        }

        $modality = $this->modalities[mb_strtolower($row['modality'])] ?? null;
        $steps = $this->steps($row);
        $messages = $this->complaints($number, $row, $area, $steps, $modality);

        if ($messages !== []) {
            return ['status' => 'error', 'messages' => $messages];
        }

        return $this->walk($canvas, $number, $row, $area, $steps, $modality, $apply);
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<array{0: string, 1: string, 2: ElementKind}>  $steps
     * @return list<string>
     */
    private function complaints(int $number, array $row, Area $area, array $steps, ?Modality $modality): array
    {
        $messages = [];

        if ($row['domain'] === '') {
            $messages[] = (string) __('csvx.domain_missing', ['line' => $number]);
        }

        if ($row['cluster'] !== '' && $area->kind === 'list') {
            $messages[] = (string) __('csvx.cluster_not_allowed', ['line' => $number]);
        }

        if ($row['modality'] !== '' && $area->kind === 'list') {
            $messages[] = (string) __('csvx.modality_not_allowed', ['line' => $number]);
        } elseif ($row['modality'] !== '' && ! $modality instanceof Modality) {
            $messages[] = (string) __('csvx.modality_unknown', ['line' => $number, 'value' => $row['modality']]);
        }

        if (mb_strlen($row['description']) > self::DESCRIPTION_MAX) {
            $messages[] = (string) __('import.text_max', ['line' => $number, 'column' => Headers::label('structure', 'description'), 'max' => self::DESCRIPTION_MAX]);
        }

        foreach ($steps as [$column, $name]) {
            if (mb_strlen($name) > self::NAME_MAX) {
                $messages[] = (string) __('import.text_max', ['line' => $number, 'column' => Headers::label('structure', $column), 'max' => self::NAME_MAX]);
            }

            if (str_contains($name, '>')) {
                $messages[] = (string) __('csvx.name_sign', ['line' => $number, 'column' => Headers::label('structure', $column)]);
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<array{0: string, 1: string, 2: ElementKind}>  $steps
     * @return array{status: string, messages: list<string>}
     */
    private function walk(Canvas $canvas, int $number, array $row, Area $area, array $steps, ?Modality $modality, bool $apply): array
    {
        $parent = null;
        $created = false;
        $last = count($steps) - 1;

        foreach ($steps as $index => [$column, $name, $kind]) {
            $key = $area->id.'|'.($parent['key'] ?? '').'|'.Element::normalise($name);
            $node = $this->nodes[$key] ?? null;

            if ($node === null) {
                $node = $this->add($canvas, $area, $kind, $parent, $key, $this->fields($row, $modality, $name, $index === $last), $apply);
                $created = true;
            } else {
                $conflict = $this->conflict($number, $column, $name, $node['element'], $kind);

                if ($conflict !== null) {
                    return $this->refusal($conflict);
                }
            }

            $parent = $node;
        }

        if ($created || $parent === null) {
            return ['status' => 'new', 'messages' => []];
        }

        $changes = $this->changes($row, $modality, $parent['element']);

        if ($changes === []) {
            return ['status' => 'unchanged', 'messages' => []];
        }

        if ($apply) {
            $this->tree->update($parent['element'], $changes);
        } else {
            $parent['element']->fill($changes);
        }

        return ['status' => 'updated', 'messages' => []];
    }

    /**
     * @param  array{element: Element, key: string}|null  $parent
     * @param  array<string, mixed>  $fields
     * @return array{element: Element, key: string}
     */
    private function add(Canvas $canvas, Area $area, ElementKind $kind, ?array $parent, string $key, array $fields, bool $apply): array
    {
        if ($apply) {
            $element = $this->tree->create($canvas, $area, $kind, $parent === null ? null : $parent['key'], $fields);
            $element->setRelation('area', $area);

            return $this->nodes[$key] = ['element' => $element, 'key' => (string) $element->getKey()];
        }

        return $this->nodes[$key] = ['element' => new Element([...$fields, 'area_id' => $area->id, 'kind' => $kind]), 'key' => $key];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function fields(array $row, ?Modality $modality, string $name, bool $deepest): array
    {
        $fields = ['name' => $name];

        if ($deepest && $row['description'] !== '') {
            $fields['description'] = $row['description'];
        }

        if ($deepest && $modality instanceof Modality) {
            $fields['modality_id'] = $modality->id;
        }

        return $fields;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function changes(array $row, ?Modality $modality, Element $element): array
    {
        $changes = [];

        if ($row['description'] !== '' && $row['description'] !== (string) $element->description) {
            $changes['description'] = $row['description'];
        }

        if ($modality instanceof Modality && (int) $modality->id !== (int) $element->modality_id) {
            $changes['modality_id'] = $modality->id;
        }

        return $changes;
    }

    private function conflict(int $number, string $column, string $name, Element $element, ElementKind $kind): ?string
    {
        $replace = ['line' => $number, 'column' => Headers::label('structure', $column), 'value' => $name];

        return match (true) {
            $element->archived_at !== null => (string) __('csvx.name_archived', $replace),
            $element->is_unknown_allocation => (string) __('csvx.name_managed', $replace),
            $element->kind !== $kind => (string) __('csvx.kind_conflict', $replace),
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $row
     * @return list<array{0: string, 1: string, 2: ElementKind}>
     */
    private function steps(array $row): array
    {
        $steps = [['domain', $row['domain'], ElementKind::Domain]];

        if ($row['cluster'] !== '') {
            $steps[] = ['cluster', $row['cluster'], ElementKind::Cluster];
        }

        if ($row['activity'] !== '') {
            $steps[] = ['activity', $row['activity'], ElementKind::Activity];
        }

        return $steps;
    }

    /**
     * @return array{status: string, messages: list<string>}
     */
    private function refusal(string $message): array
    {
        return ['status' => 'error', 'messages' => [$message]];
    }

    /**
     * @return array<string, Area>
     */
    private function areaLookup(): array
    {
        $lookup = [];

        foreach (Area::query()->get() as $area) {
            $lookup[mb_strtolower((string) $area->key)] = $area;
            $lookup[mb_strtolower((string) __('areas.'.$area->key.'.name'))] = $area;
        }

        return $lookup;
    }

    /**
     * @return array<string, Modality>
     */
    private function modalityLookup(): array
    {
        $lookup = [];

        foreach (Modality::query()->get() as $modality) {
            $lookup[mb_strtolower((string) $modality->key)] = $modality;
            $lookup[mb_strtolower((string) $modality->letter)] = $modality;
        }

        return $lookup;
    }

    /**
     * @return array<string, array{element: Element, key: string}>
     */
    private function nodeLookup(Canvas $canvas): array
    {
        $lookup = [];

        foreach (Element::query()->with('area')->where('canvas_id', $canvas->getKey())->get() as $element) {
            $key = $element->area_id.'|'.($element->parent_id ?? '').'|'.$element->name_normalised;

            if (! isset($lookup[$key]) || $element->archived_at === null) {
                $lookup[$key] = ['element' => $element, 'key' => (string) $element->getKey()];
            }
        }

        return $lookup;
    }
}
