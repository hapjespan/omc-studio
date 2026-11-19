<?php

namespace App\Services\Exchange;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Snapshot\SnapshotContent;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class FullImportValidator
{
    public const DEPTH = 64;

    private const MAX_ERRORS = 1000;

    private const NAME = 150;

    private const STUB = ['statements' => [], 'elements' => [], 'layout' => null, 'allocation_sets' => [], 'finalised_years' => []];

    /** @var list<array{location: string, message: string}> */
    private array $errors = [];

    private int $count = 0;

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
            'archived_at' => $entry['archived_at'] ?? null,
            'data' => $result['data'],
            'canvases' => $this->canvases($entry['canvases'], $at),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $canvases
     * @return list<array<string, mixed>>
     */
    private function canvases(array $canvases, string $at): array
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
                'archived_at' => $canvas['archived_at'] ?? null,
                'block' => $result['canvas'],
                'snapshots' => $this->snapshots($canvas['snapshots'] ?? [], $where.'.snapshots'),
            ];
        }

        return $prepared;
    }

    /** @return list<array<string, mixed>> */
    private function snapshots(mixed $node, string $at): array
    {
        $prepared = [];

        if (! is_array($node) || ! array_is_list($node)) {
            $this->fail($at, 'jsonx.not_list');

            return $prepared;
        }

        foreach ($node as $index => $snapshot) {
            $where = $at.'['.$index.']';
            $name = is_array($snapshot) ? ($snapshot['name'] ?? null) : null;
            $content = is_array($snapshot) ? ($snapshot['content'] ?? null) : null;
            $checksum = is_array($snapshot) ? ($snapshot['checksum'] ?? null) : null;
            $remark = is_array($snapshot) ? ($snapshot['remark'] ?? null) : null;

            $sound = $this->flag(is_array($snapshot), $where, 'jsonx.not_object')
                && $this->flag(is_string($name) && trim($name) !== '' && mb_strlen(trim($name)) <= self::NAME, $where.'.name', 'jsonx.required')
                && $this->flag(is_int($snapshot['fiscal_year'] ?? null) && is_int($snapshot['schema_version'] ?? null), $where.'.fiscal_year', 'jsonx.unknown_value')
                && $this->flag($remark === null || is_string($remark), $where.'.remark', 'jsonx.not_text')
                && $this->flag(is_array($content) && is_string($checksum), $where.'.content', 'jsonx.not_object')
                && $this->moment($snapshot['created_at'] ?? null, $where.'.created_at', true)
                && $this->moment($snapshot['archived_at'] ?? null, $where.'.archived_at');

            if (! $sound) {
                continue;
            }

            $encoded = $this->content->encode($content);

            hash_equals($checksum, $this->content->checksum($encoded)) || $this->refuse('fullx.snapshot_damaged', ['name' => trim((string) $name)]);

            $prepared[] = [
                'name' => trim((string) $name),
                'fiscal_year' => $snapshot['fiscal_year'],
                'remark' => $remark,
                'created_at' => $snapshot['created_at'],
                'archived_at' => $snapshot['archived_at'] ?? null,
                'schema_version' => $snapshot['schema_version'],
                'content' => $encoded,
                'checksum' => $checksum,
            ];
        }

        return $prepared;
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
            $location = str_starts_with($error['location'], 'canvas.') ? substr($error['location'], 7) : $error['location'];
            count($this->errors) < self::MAX_ERRORS && $this->errors[] = ['location' => $at.$location, 'message' => $error['message']];
        }
    }

    /** @param array<string, bool> $seen */
    private function once(string $key, array &$seen): bool
    {
        if (isset($seen[$key])) {
            return false;
        }

        $seen[$key] = true;

        return true;
    }

    private function moment(mixed $value, string $location, bool $required = false): bool
    {
        if ($value === null) {
            return $required ? $this->flag(false, $location, 'jsonx.required') : true;
        }

        $date = is_string($value) ? DateTimeImmutable::createFromFormat(DATE_ATOM, $value) : false;

        return $this->flag($date !== false && $date->format(DATE_ATOM) === $value, $location, 'jsonx.date_invalid');
    }

    private function flag(bool $sound, string $location, string $key): bool
    {
        $sound || $this->fail($location, $key);

        return $sound;
    }

    /** @param array<string, int|string> $replace */
    private function fail(string $location, string $key, array $replace = []): void
    {
        $this->count++;
        count($this->errors) < self::MAX_ERRORS && $this->errors[] = ['location' => $location, 'message' => (string) __($key, ['location' => $location, ...$replace])];
    }

    /** @param array<string, int|string> $replace */
    private function refuse(string $key, array $replace = []): never
    {
        throw ValidationException::withMessages(['file' => (string) __($key, $replace)]);
    }
}
