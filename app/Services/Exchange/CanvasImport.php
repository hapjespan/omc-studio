<?php

namespace App\Services\Exchange;

use App\Models\Organisation;
use App\Services\Audit\AuditContext;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class CanvasImport
{
    use WritableGuard;

    public function __construct(
        private readonly CanvasImportValidator $validator,
        private readonly CanvasWriter $writer,
        private readonly SerialisedWrite $writes,
        private readonly AuditContext $context,
    ) {}

    /**
     * @return array{errors: list<array{location: string, message: string}>, count: int, totals: array<string, int>}
     */
    public function preview(string $content, ?Organisation $target, string $canvasName, ?string $organisationName): array
    {
        $target !== null && $this->assertWritable($target);

        $result = $this->check($content, $target, $canvasName, $organisationName);

        return ['errors' => $result['errors'], 'count' => $result['count'], 'totals' => $this->totals($result['data'], $result['reused'])];
    }

    /**
     * @return array{canvas_id: int, organisation_id: int, totals: array<string, int>}
     */
    public function commit(string $content, ?Organisation $target, string $canvasName, ?string $organisationName): array
    {
        $result = $this->check($content, $target, $canvasName, $organisationName);

        if ($result['errors'] !== []) {
            $this->refuseFile('import.refused_errors');
        }

        $data = $result['data'];

        return $this->writes->run($target === null ? [] : [$target], function () use ($target, $data, $canvasName, $organisationName) {
            if ($target !== null) {
                $this->assertWritable($target);
            }

            return $this->context->during('imported', function () use ($target, $data, $canvasName, $organisationName) {
                $found = $this->validator->conflicts($data, $target, $canvasName, $organisationName);

                if ($found['errors'] !== []) {
                    $this->refuseFile('import.refused_changed');
                }

                return $this->build($target ?? $this->writer->organisation((string) $organisationName), $data, $canvasName, $found['reused']);
            });
        });
    }

    /**
     * @return array{data: array<string, mixed>, errors: list<array{location: string, message: string}>, count: int, reused: list<string>}
     */
    private function check(string $content, ?Organisation $target, string $canvasName, ?string $organisationName): array
    {
        $result = $this->validator->check($content, $target, $canvasName, $organisationName);

        if ($result['data'] === null) {
            $this->refuseFile('jsonx.refused_file');
        }

        return ['data' => $result['data'], 'errors' => $result['errors'], 'count' => $result['count'], 'reused' => $result['reused']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $reused
     * @return array{canvas_id: int, organisation_id: int, totals: array<string, int>}
     */
    private function build(Organisation $organisation, array $data, string $canvasName, array $reused): array
    {
        $organisationId = (int) $organisation->getKey();
        [$years, $owners, $applications] = $this->writer->prepare($organisationId, $data);
        $records = $this->writer->costRecords($organisationId, $data['cost_records'], $reused, $years, $owners, $applications);
        $canvas = $this->writer->canvas($organisationId, $canvasName, $data['canvas'], $records, $years);

        return ['canvas_id' => (int) $canvas->getKey(), 'organisation_id' => $organisationId, 'totals' => $this->totals($data, $reused)];
    }

    /**
     * @param  ?array<string, mixed>  $data
     * @param  list<string>  $reused
     * @return array<string, int>
     */
    private function totals(?array $data, array $reused): array
    {
        $canvas = is_array($data['canvas'] ?? null) ? $data['canvas'] : [];
        $elements = $this->listOf($canvas['elements'] ?? null);

        return [
            'cost_records' => count($this->listOf($data['cost_records'] ?? null)),
            'reused' => count($reused),
            'elements' => count(array_filter($elements, fn ($row) => ! is_array($row) || ($row['is_unknown_allocation'] ?? null) !== true)),
            'statements' => count($this->listOf($canvas['statements'] ?? null)),
            'allocation_sets' => count($this->listOf($canvas['allocation_sets'] ?? null)),
            'finalised_years' => count($this->listOf($canvas['finalised_years'] ?? null)),
        ];
    }

    /** @return list<mixed> */
    private function listOf(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    private function refuseFile(string $key): never
    {
        throw ValidationException::withMessages(['file' => (string) __($key)]);
    }
}
