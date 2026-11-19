<?php

namespace App\Services\Exchange;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Audit\AuditContext;
use App\Services\Support\SerialisedWrite;
use Illuminate\Validation\ValidationException;

class FullImport
{
    public function __construct(
        private readonly FullImportValidator $validator,
        private readonly CanvasWriter $writer,
        private readonly SerialisedWrite $writes,
        private readonly AuditContext $context,
    ) {}

    /**
     * @return array{errors: list<array{location: string, message: string}>, count: int, totals: array<string, int>}
     */
    public function preview(string $content): array
    {
        $result = $this->check($content);

        return ['errors' => $result['errors'], 'count' => $result['count'], 'totals' => $result['totals']];
    }

    /**
     * @return array{totals: array<string, int>}
     */
    public function commit(string $content): array
    {
        $result = $this->check($content);

        if ($result['count'] > 0) {
            $this->refuse('import.refused_errors');
        }

        $this->writes->run([], function () use ($result) {
            $this->assertEmpty();

            $this->context->during('imported', function () use ($result) {
                foreach ($result['organisations'] as $entry) {
                    $this->organisation($entry);
                }

                foreach ($result['templates'] as $template) {
                    $this->writer->template($template['block'], $template['name']);
                }
            });
        });

        return ['totals' => $result['totals']];
    }

    /**
     * @return array{organisations: list<array<string, mixed>>, templates: list<array{name: string, block: array<string, mixed>}>, errors: list<array{location: string, message: string}>, count: int, totals: array<string, int>}
     */
    private function check(string $content): array
    {
        $this->assertEmpty();

        $result = $this->validator->check($content);

        return [...$result, 'totals' => $this->totals($result['organisations'], $result['templates'])];
    }

    /** @param array<string, mixed> $entry */
    private function organisation(array $entry): void
    {
        $organisation = $this->writer->organisation((string) $entry['name']);
        $organisationId = (int) $organisation->getKey();

        [$years, $owners, $applications] = $this->writer->prepare($organisationId, $entry['data']);
        $records = $this->writer->costRecords($organisationId, $entry['data']['cost_records'], [], $years, $owners, $applications, true);

        foreach ($entry['canvases'] as $block) {
            $canvas = $this->writer->canvas($organisationId, (string) $block['name'], $block['block'], $records, $years);

            $this->archive($canvas, $block['archived_at']);
        }

        $this->archive($organisation, $entry['archived_at']);
    }

    private function archive(Canvas|Organisation $model, mixed $at): void
    {
        $at === null || $this->context->during('archived', fn () => $model->update(['archived_at' => $at]));
    }

    private function assertEmpty(): void
    {
        Organisation::query()->exists() && $this->refuse('fullx.not_empty', 'rule');
    }

    /**
     * @param  list<array<string, mixed>>  $organisations
     * @param  list<array{name: string, block: array<string, mixed>}>  $templates
     * @return array<string, int>
     */
    private function totals(array $organisations, array $templates): array
    {
        return [
            'organisations' => count($organisations),
            'canvases' => array_sum(array_map(fn (array $entry) => count($entry['canvases']), $organisations)),
            'cost_records' => array_sum(array_map(fn (array $entry) => is_array($entry['data']['cost_records'] ?? null) ? count($entry['data']['cost_records']) : 0, $organisations)),
            'templates' => count($templates),
        ];
    }

    private function refuse(string $key, string $field = 'file'): never
    {
        throw ValidationException::withMessages([$field => (string) __($key)]);
    }
}
