<?php

namespace App\Services\Exchange;

use App\Services\Audit\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CostRecordBatch
{
    private const CHUNK = 400;

    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    /** @var list<array<string, mixed>> */
    private array $figures = [];

    public function __construct(private readonly AuditContext $context, private readonly int $organisationId) {}

    public function has(string $key): bool
    {
        return isset($this->records[$key]);
    }

    /** @param array<string, mixed> $attributes */
    public function record(string $key, string $code, array $attributes): void
    {
        $this->records[$key] = ['id' => (string) Str::uuid(), 'organisation_id' => $this->organisationId, 'code' => $code, ...$attributes, 'code_normalised' => $key];
    }

    /** @param array<string, ?int> $figures */
    public function figure(string $key, int $yearId, array $figures): void
    {
        $this->figures[] = ['cost_record_id' => $this->records[$key]['id'], 'fiscal_year_id' => $yearId, ...$figures];
    }

    public function flush(): void
    {
        $now = now();

        foreach (array_chunk($this->records, self::CHUNK) as $chunk) {
            DB::table('cost_records')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $chunk));
            $this->audit('cost_record', array_map(fn (array $row) => [$row['id'], (string) $row['name'], $this->changes($row, ['id'])], $chunk));
        }

        foreach (array_chunk($this->figures, self::CHUNK) as $chunk) {
            DB::table('yearly_figures')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $chunk));
            $ids = DB::table('yearly_figures')->whereIn('cost_record_id', array_unique(array_column($chunk, 'cost_record_id')))->get(['id', 'cost_record_id', 'fiscal_year_id'])
                ->mapWithKeys(fn (object $row) => [$row->cost_record_id.'|'.$row->fiscal_year_id => (string) $row->id]);
            $this->audit('yearly_figure', array_map(function (array $row) use ($ids) {
                $id = $ids[$row['cost_record_id'].'|'.$row['fiscal_year_id']];

                return [$id, $id, $this->changes($row, [])];
            }, $chunk));
        }

        $this->records = [];
        $this->figures = [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $except
     * @return array<string, array{null, mixed}>
     */
    private function changes(array $row, array $except): array
    {
        return array_map(fn ($value) => [null, $value], array_diff_key($row, array_flip($except)));
    }

    /** @param list<array{string, string, array<string, array{null, mixed}>}> $entries */
    private function audit(string $subject, array $entries): void
    {
        $now = now();
        $organisationId = $this->organisationId;

        DB::table('audit_entries')->insert(array_map(fn (array $entry) => [
            'occurred_at' => $now,
            'actor_type' => $this->context->actorType()->value,
            'actor_id' => $this->context->actorId(),
            'action' => $this->context->action('created'),
            'subject_type' => $subject,
            'subject_id' => $entry[0],
            'subject_label' => Str::limit($entry[1], 150, ''),
            'organisation_id' => $organisationId,
            'canvas_id' => null,
            'changes' => json_encode($entry[2]),
        ], $entries));
    }
}
