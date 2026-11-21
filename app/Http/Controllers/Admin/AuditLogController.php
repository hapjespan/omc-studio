<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActorType;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogQuery;
use App\Support\DateRangeFilter;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    private const DERIVED = ['name_normalised', 'scope_key'];

    private const FILTERS = ['organisation_id', 'canvas_id', 'subject_type', 'actor_id', 'from', 'to'];

    private const KINDS = ['user', 'organisation', 'canvas', 'element', 'statement', 'area', 'application', 'owner', 'cost_record', 'allocation_set', 'finalised_year', 'fiscal_year', 'yearly_figure', 'snapshot'];

    public function __invoke(Request $request, AuditLogQuery $query): Response
    {
        $validator = Validator::make($request->only(self::FILTERS), [
            'organisation_id' => ['nullable', 'integer'],
            'canvas_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'in:'.implode(',', self::KINDS)],
            'actor_id' => ['nullable', 'integer'],
            ...DateRangeFilter::rules(),
        ], [
            'integer' => __('audit_log.error.choose'),
            'in' => __('audit_log.error.choose'),
            ...DateRangeFilter::messages(),
        ], [
            'organisation_id' => __('audit_log.filter.organisation'),
            'canvas_id' => __('audit_log.filter.canvas'),
            'subject_type' => __('audit_log.filter.kind'),
            'actor_id' => __('audit_log.filter.user'),
            ...DateRangeFilter::attributes(),
        ]);
        $filters = $request->only(self::FILTERS);
        $page = $query->paginate($validator->valid());

        return Inertia::render('Admin/AuditLog', [
            'entries' => $page->getCollection()->map(fn (AuditEntry $entry) => $this->present($entry))->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'filters' => $filters,
            'filterErrors' => array_map(fn ($messages) => $messages[0], $validator->errors()->messages()),
            'options' => [
                'organisations' => Organisation::query()->orderBy('name')->get(['id', 'name']),
                'canvases' => Canvas::query()->orderBy('name')->get(['id', 'name']),
                'users' => User::query()->orderBy('name')->get(['id', 'name']),
                'kinds' => self::KINDS,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(AuditEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'occurred_at' => Format::formatDate($entry->occurred_at->toIso8601String()).' '.Format::formatTime($entry->occurred_at->toIso8601String()),
            'actor' => match (true) {
                $entry->actor_type === ActorType::System => ['kind' => 'system', 'name' => null],
                $entry->actor === null => ['kind' => 'deleted', 'name' => null],
                default => ['kind' => 'user', 'name' => $entry->actor->name],
            },
            'action' => $entry->action,
            'subject_type' => $entry->subject_type,
            'subject_label' => $entry->subject_label,
            'canvas' => $entry->canvas?->name,
            'changes' => collect($entry->changes)
                ->reject(fn ($pair, $field) => in_array($field, self::DERIVED, true))
                ->map(fn ($pair, $field) => ['field' => $field, 'old' => $this->text($pair[0] ?? null), 'new' => $this->text($pair[1] ?? null)])
                ->values(),
        ];
    }

    private function text(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
