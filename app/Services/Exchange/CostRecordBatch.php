<?php

namespace App\Services\Exchange;

use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CostRecordBatch
{
    private const CHUNK = 400;

    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    /** @var list<array<string, mixed>> */
    private array $figures = [];

    public function __construct(private readonly AuditContext $context, private readonly AuditTrail $trail, private readonly int $organisationId, private readonly bool $keepIds = false) {}

    public function has(string $key): bool
    {
        return isset($this->records[$key]);
    }

    public function idOf(string $key): string
    {
        return (string) $this->records[$key]['id'];
    }

    /** @param array<string, mixed> $attributes */
    public function record(string $key, ?string $code, array $attributes): void
    {
        $this->records[$key] = ['id' => $this->keepIds ? $key : (string) Str::uuid(), 'organisation_id' => $this->organisationId, 'code' => $code, ...$attributes, 'code_normalised' => $code === null ? null : mb_strtolower($code)];
    }

    /** @param array<string, mixed> $figures */
    public function figure(string $key, int $yearId, array $figures): void
    {
        $this->figures[] = ['cost_record_id' => $this->records[$key]['id'], 'fiscal_year_id' => $yearId, ...$figures];
    }

    public function flush(): void
    {
        $now = now();

        foreach (array_chunk($this->records, self::CHUNK) as $chunk) {
            DB::table('cost_records')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $chunk));
            $this->trail->recordMany('cost_record', $this->context->action('created'), $this->organisationId, array_map(fn (array $row) => [$row['id'], (string) $row['name'], $this->changes($row, ['id'])], $chunk));
        }

        foreach (array_chunk($this->figures, self::CHUNK) as $chunk) {
            DB::table('yearly_figures')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $chunk));
            $ids = DB::table('yearly_figures')->whereIn('cost_record_id', array_unique(array_column($chunk, 'cost_record_id')))->get(['id', 'cost_record_id', 'fiscal_year_id'])
                ->mapWithKeys(fn (object $row) => [$row->cost_record_id.'|'.$row->fiscal_year_id => (string) $row->id]);
            $this->trail->recordMany('yearly_figure', $this->context->action('created'), null, array_map(function (array $row) use ($ids) {
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
}
