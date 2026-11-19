<?php

namespace App\Services\Exchange;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Audit\AuditContext;
use App\Services\Support\SerialisedWrite;
use Illuminate\Validation\ValidationException;
use JsonException;

class FullImport
{
    private const STUB = ['name' => 'Import', 'statements' => [], 'elements' => [], 'layout' => null, 'allocation_sets' => [], 'finalised_years' => []];

    public function __construct(
        private readonly CanvasImportValidator $validator,
        private readonly CanvasImport $import,
        private readonly SerialisedWrite $writes,
        private readonly AuditContext $context,
    ) {}

    /**
     * @return array{errors: list<array{location: string, message: string}>, count: int, totals: array<string, int>}
     */
    public function preview(string $content): array
    {
        $this->assertEmpty();
        $data = $this->decode($content);
        $errors = [];
        $count = 0;
        $names = [];

        foreach ($data['organisations'] as $index => $entry) {
            $name = Organisation::normalise((string) ($entry['organisation']['name'] ?? ''));
            $at = 'organisations['.$index.']';

            if (isset($names[$name])) {
                $count++;
                $errors[] = ['location' => $at.'.organisation.name', 'message' => __('jsonx.organisation_name_taken')];
            }

            $names[$name] = true;
            $count += $this->organisation($entry, $at, $errors);
        }

        foreach ($data['templates'] as $index => $template) {
            $at = 'templates['.$index.']';
            $count += $this->collect($this->validator->validate($this->document($this->templateDocument($template))), $at, $errors);
        }

        return ['errors' => $errors, 'count' => $count, 'totals' => $this->totals($data)];
    }

    /**
     * @return array{totals: array<string, int>}
     */
    public function commit(string $content): array
    {
        $result = $this->preview($content);

        if ($result['count'] > 0) {
            $this->refuse('import.refused_errors');
        }

        $data = $this->decode($content);

        $this->writes->run([], function () use ($data) {
            $this->assertEmpty();

            $this->context->during('imported', function () use ($data) {
                foreach ($data['organisations'] as $entry) {
                    $this->build($entry);
                }

                foreach ($data['templates'] as $template) {
                    $this->template($template);
                }
            });
        });

        return ['totals' => $result['totals']];
    }

    /** @param array<string, mixed> $entry */
    private function build(array $entry): void
    {
        $documents = $this->documents($entry);
        $first = $this->validator->validate($documents[0]);
        $organisation = $this->import->foundation((string) $entry['organisation']['name'], $first['data'] ?? []);

        foreach ($entry['canvases'] as $position => $canvas) {
            $result = $this->import->commit($documents[$position], $organisation, (string) $canvas['name'], null);
            $this->archive(Canvas::findOrFail($result['canvas_id']), $canvas['archived_at'] ?? null);
        }

        $this->archive($organisation, $entry['archived_at'] ?? null);
    }

    private function archive(Canvas|Organisation $model, mixed $at): void
    {
        $at === null || $this->context->during('archived', fn () => $model->update(['archived_at' => $at]));
    }

    /** @param array<string, mixed> $template */
    private function template(array $template): void
    {
        $name = (string) $template['name'];
        $taken = Canvas::where('scope_key', 'template')->where('name_normalised', Canvas::normalise($name))->exists();
        $result = $this->validator->validate($this->document($this->templateDocument($template)));

        $this->import->template(['name' => $name, ...array_intersect_key($result['data']['canvas'] ?? [], array_flip(['statements', 'elements']))], $taken ? $name.__('fullx.imported_suffix') : $name);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<array{location: string, message: string}>  $errors
     */
    private function organisation(array $entry, string $at, array &$errors): int
    {
        $count = 0;
        $names = [];
        $documents = $this->documents($entry);

        foreach ($documents as $position => $document) {
            $count += $this->collect($this->validator->validate($document), $at.($entry['canvases'] === [] ? '' : '.canvases['.$position.']'), $errors);
            $name = Canvas::normalise((string) ($entry['canvases'][$position]['name'] ?? ''));

            if ($entry['canvases'] !== [] && isset($names[$name])) {
                $count++;
                $errors[] = ['location' => $at.'.canvases['.$position.'].name', 'message' => __('jsonx.canvas_name_taken', ['location' => 'canvas_name'])];
            }

            $names[$name] = true;
        }

        return $count;
    }

    /**
     * @param  array{errors: list<array{location: string, message: string}>, count: int}  $result
     * @param  list<array{location: string, message: string}>  $errors
     */
    private function collect(array $result, string $at, array &$errors): int
    {
        foreach ($result['errors'] as $error) {
            count($errors) < 1000 && $errors[] = ['location' => $at.'.'.$error['location'], 'message' => $error['message']];
        }

        return $result['count'];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function documents(array $entry): array
    {
        $blocks = $entry['canvases'] === [] ? [self::STUB] : $entry['canvases'];

        return array_map(fn (array $canvas) => $this->document([
            'organisation' => $entry['organisation'],
            'cost_records' => $entry['cost_records'],
            'canvas' => array_diff_key($canvas, ['archived_at' => null]),
        ]), $blocks);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function templateDocument(array $template): array
    {
        return [
            'organisation' => ['name' => 'Template', 'owners' => [], 'applications' => [], 'fiscal_years' => []],
            'cost_records' => [],
            'canvas' => [...self::STUB, ...$template],
        ];
    }

    /** @param array<string, mixed> $parts */
    private function document(array $parts): string
    {
        return json_encode(['format' => CanvasExport::FORMAT, 'format_version' => 1, ...$parts], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{organisations: list<array<string, mixed>>, templates: list<array<string, mixed>>}
     */
    private function decode(string $content): array
    {
        try {
            $data = strlen($content) > CanvasImportValidator::MAX_BYTES ? null : json_decode($content, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        if (! is_array($data) || ($data['format'] ?? null) !== FullExport::FORMAT || ($data['format_version'] ?? null) !== 1 || ! $this->listOfObjects($data['organisations'] ?? null) || ! $this->listOfObjects($data['templates'] ?? null)) {
            $this->refuse('fullx.refused_file');
        }

        foreach ($data['organisations'] as $entry) {
            if (! is_array($entry['organisation'] ?? null) || ! $this->listOfObjects($entry['cost_records'] ?? null) || ! $this->listOfObjects($entry['canvases'] ?? null)) {
                $this->refuse('fullx.refused_file');
            }
        }

        return ['organisations' => $data['organisations'], 'templates' => $data['templates']];
    }

    private function listOfObjects(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, fn ($item) => ! is_array($item)) === [];
    }

    private function assertEmpty(): void
    {
        Organisation::query()->exists() && $this->refuse('fullx.not_empty');
    }

    /**
     * @param  array{organisations: list<array<string, mixed>>, templates: list<array<string, mixed>>}  $data
     * @return array<string, int>
     */
    private function totals(array $data): array
    {
        return [
            'organisations' => count($data['organisations']),
            'canvases' => array_sum(array_map(fn (array $entry) => count($entry['canvases']), $data['organisations'])),
            'cost_records' => array_sum(array_map(fn (array $entry) => count($entry['cost_records']), $data['organisations'])),
            'templates' => count($data['templates']),
        ];
    }

    private function refuse(string $key): never
    {
        throw ValidationException::withMessages(['file' => (string) __($key)]);
    }
}
