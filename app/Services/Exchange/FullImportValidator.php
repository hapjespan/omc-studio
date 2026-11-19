<?php

namespace App\Services\Exchange;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Exchange\Concerns\CollectsFileErrors;
use App\Services\Snapshot\SnapshotContent;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class FullImportValidator
{
    use CollectsFileErrors;

    public const DEPTH = 64;

    private const NAME = 150;

    private const TEXT = 2000;

    private const CHECKSUM = 64;

    private const YEARS = [2000, 2100];

    private const STUB = ['statements' => [], 'elements' => [], 'layout' => null, 'allocation_sets' => [], 'finalised_years' => []];

    /** @var array<string, bool> */
    private array $recordIds = [];

    public function __construct(private readonly CanvasImportValidator $validator, private readonly SnapshotContent $content) {}

    /**
     * @return array{organisations: list<array<string, mixed>>, templates: list<array{name: string, block: array<string, mixed>}>, errors: list<array{location: string, message: string}>, count: int}
     */
    public function check(string $content): array
    {
        $this->errors = [];
        $this->count = 0;
        $this->recordIds = [];

        $data = $this->decode($content);
        $organisations = [];
        $names = [];

        foreach (array_keys($data['organisations']) as $index) {
            $entry = $data['organisations'][$index];
            unset($data['organisations'][$index]);
            $organisations[] = $this->entry($entry, 'organisations['.$index.']', $names);
        }

        return ['organisations' => $organisations, 'templates' => $this->templates($data['templates']), 'errors' => $this->errors, 'count' => $this->count];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, bool>  $names
     * @return array<string, mixed>
     */
    private function entry(array $entry, string $at, array &$names): array
    {
        $result = $this->validator->foundation(['organisation' => $entry['organisation'] ?? null, 'cost_records' => $entry['cost_records'] ?? null]);
        $this->collect($result['errors'], $at.'.', $result['count']);

        $name = $result['data']['organisation']['name'] ?? null;

        is_string($name) && ! $this->once(Organisation::normalise($name), $names) && $this->fail($at.'.organisation.name', 'jsonx.organisation_name_taken');

        $this->records($result['data']['cost_records'] ?? null, $at);
        $this->moment($entry['archived_at'] ?? null, $at.'.archived_at');

        return [
            'name' => $name,
            'archived_at' => $this->utc($entry['archived_at'] ?? null),
            'data' => $result['data'],
            'canvases' => $this->canvases($entry['canvases'], $at, $this->years($result['data']['organisation']['fiscal_years'] ?? null)),
        ];
    }

    /** @return list<int> */
    private function years(mixed $node): array
    {
        return array_values(array_filter(is_array($node) ? $node : [], fn (mixed $year) => is_int($year) && $year >= self::YEARS[0] && $year <= self::YEARS[1]));
    }

    /**
     * @param  list<array<string, mixed>>  $canvases
     * @param  list<int>  $years
     * @return list<array<string, mixed>>
     */
    private function canvases(array $canvases, string $at, array $years): array
    {
        $prepared = [];
        $names = [];

        foreach ($canvases as $index => $canvas) {
            $where = $at.'.canvases['.$index.']';
            $result = $this->validator->block([...self::STUB, ...array_diff_key($canvas, ['archived_at' => null, 'snapshots' => null])]);
            $this->collect($result['errors'], $where.'.', $result['count']);

            $name = is_array($result['canvas']) ? ($result['canvas']['name'] ?? null) : null;

            is_string($name) && ! $this->once(Canvas::normalise($name), $names) && $this->fail($where.'.name', 'jsonx.canvas_name_taken');
            $this->moment($canvas['archived_at'] ?? null, $where.'.archived_at');

            $prepared[] = [
                'name' => $name,
                'archived_at' => $this->utc($canvas['archived_at'] ?? null),
                'block' => $result['canvas'],
                'snapshots' => $this->snapshots($canvas['snapshots'] ?? [], $where.'.snapshots', $years),
            ];
        }

        return $prepared;
    }

    /**
     * @param  list<int>  $years
     * @return list<array<string, mixed>>
     */
    private function snapshots(mixed $node, string $at, array $years): array
    {
        $prepared = [];

        if (! is_array($node) || ! array_is_list($node)) {
            $this->fail($at, 'jsonx.not_list');

            return $prepared;
        }

        foreach ($node as $index => $snapshot) {
            $where = $at.'['.$index.']';

            if (! is_array($snapshot)) {
                $this->fail($where, 'jsonx.not_object');

                continue;
            }

            $row = $this->snapshot($snapshot, $where, $years);
            $row === null || $prepared[] = $row;
        }

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<int>  $years
     * @return ?array<string, mixed>
     */
    private function snapshot(array $snapshot, string $at, array $years): ?array
    {
        $name = $snapshot['name'] ?? null;
        $content = $snapshot['content'] ?? null;
        $checksum = $snapshot['checksum'] ?? null;

        $sound = $this->text($name, $at.'.name', self::NAME, true);
        $sound = $this->text($snapshot['remark'] ?? null, $at.'.remark', self::TEXT) && $sound;
        $sound = $this->flag(in_array($snapshot['fiscal_year'] ?? null, $years, true), $at.'.fiscal_year', 'jsonx.year_unknown') && $sound;
        $sound = $this->flag(($snapshot['schema_version'] ?? null) === SnapshotContent::SCHEMA_VERSION, $at.'.schema_version', 'jsonx.unknown_value') && $sound;
        $sound = $this->flag(is_string($checksum) && strlen($checksum) === self::CHECKSUM, $at.'.checksum', 'jsonx.unknown_value') && $sound;
        $sound = $this->flag(is_array($content) && $content !== [], $at.'.content', 'jsonx.not_object') && $sound;
        $sound = $this->moment($snapshot['created_at'] ?? null, $at.'.created_at', true) && $sound;
        $sound = $this->moment($snapshot['archived_at'] ?? null, $at.'.archived_at') && $sound;

        if (! $sound) {
            return null;
        }

        $encoded = $this->content->encode($content);

        if (! hash_equals((string) $checksum, $this->content->checksum($encoded))) {
            $this->fail($at.'.checksum', 'fullx.snapshot_damaged', ['name' => trim((string) $name)]);

            return null;
        }

        return [
            'name' => trim((string) $name),
            'fiscal_year' => $snapshot['fiscal_year'],
            'remark' => is_string($snapshot['remark'] ?? null) ? trim($snapshot['remark']) : null,
            'created_at' => $this->utc($snapshot['created_at']),
            'archived_at' => $this->utc($snapshot['archived_at'] ?? null),
            'schema_version' => $snapshot['schema_version'],
            'content' => $encoded,
            'checksum' => $checksum,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $templates
     * @return list<array{name: string, block: array<string, mixed>}>
     */
    private function templates(array $templates): array
    {
        $taken = Canvas::where('scope_key', 'template')->pluck('name_normalised')->flip()->map(fn () => true)->all();
        $prepared = [];

        foreach ($templates as $index => $template) {
            $at = 'templates['.$index.']';
            $result = $this->validator->block([...self::STUB, ...$template]);
            $this->collect($result['errors'], $at.'.', $result['count']);

            $name = is_array($result['canvas']) ? ($result['canvas']['name'] ?? null) : null;

            if (! is_string($name)) {
                continue;
            }

            $final = $this->templateName($name, $taken, $at);
            $final === null || $prepared[] = ['name' => $final, 'block' => $result['canvas']];
        }

        return $prepared;
    }

    /** @param array<string, bool> $taken */
    private function templateName(string $name, array &$taken, string $at): ?string
    {
        foreach ([$name, $this->suffixed($name)] as $candidate) {
            if ($this->once(Canvas::normalise($candidate), $taken)) {
                return $candidate;
            }
        }

        $this->fail($at.'.name', 'fullx.template_name_taken');

        return null;
    }

    private function suffixed(string $name): string
    {
        $suffix = (string) __('fullx.imported_suffix');

        return mb_substr(trim($name), 0, self::NAME - mb_strlen($suffix)).$suffix;
    }

    private function records(mixed $records, string $at): void
    {
        if (! is_array($records)) {
            return;
        }

        $seen = [];

        foreach ($records as $index => $record) {
            $id = is_array($record) ? ($record['id'] ?? null) : null;

            if (! is_string($id) || trim($id) === '') {
                continue;
            }

            if (! Str::isUuid($id)) {
                $this->fail($at.'.cost_records['.$index.'].id', 'fullx.record_id_invalid');

                continue;
            }

            $key = mb_strtolower($id);

            (isset($this->recordIds[$key]) || (isset($seen[$key]) && $seen[$key] !== $id)) && $this->fail($at.'.cost_records['.$index.'].id', 'jsonx.duplicate');
            $seen[$key] = $id;
        }

        $this->recordIds = [...$this->recordIds, ...array_map(fn () => true, $seen)];
    }

    /**
     * @return array{organisations: list<array<string, mixed>>, templates: list<array<string, mixed>>}
     */
    private function decode(string $content): array
    {
        try {
            $data = strlen($content) > CanvasImportValidator::MAX_BYTES ? null : json_decode($content, true, self::DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        if (! is_array($data) || ($data['format'] ?? null) !== FullExport::FORMAT || ($data['format_version'] ?? null) !== 1 || ! $this->listOfObjects($data['organisations'] ?? null) || ! $this->listOfObjects($data['templates'] ?? null)) {
            $this->refuse('fullx.refused_file');
        }

        foreach ($data['organisations'] as $entry) {
            $this->listOfObjects($entry['canvases'] ?? null) || $this->refuse('fullx.refused_file');
        }

        return ['organisations' => $data['organisations'], 'templates' => $data['templates']];
    }

    private function listOfObjects(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, fn ($item) => ! is_array($item)) === [];
    }

    /**
     * @param  list<array{location: string, message: string}>  $errors
     */
    private function collect(array $errors, string $at, int $count): void
    {
        $this->count += $count;

        foreach ($errors as $error) {
            $location = $at.(str_starts_with($error['location'], 'canvas.') ? substr($error['location'], 7) : $error['location']);
            count($this->errors) < self::MAX_ERRORS && $this->errors[] = ['location' => $location, 'message' => str_replace($error['location'], $location, $error['message'])];
        }
    }

    /** @param array<string, bool> $seen */

    /** @param array<string, int|string> $replace */

    /** @param array<string, int|string> $replace */
    private function refuse(string $key, array $replace = []): never
    {
        throw ValidationException::withMessages(['file' => (string) __($key, $replace)]);
    }
}
