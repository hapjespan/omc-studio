<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Models\SystemNotice;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class SystemNoticeController extends Controller
{
    private const FILTERS = ['kind', 'canvas_id', 'from', 'to'];

    private const KINDS = ['closing_check_failed', 'snapshot_damaged', 'repeated_lockouts'];

    public function index(Request $request): Response
    {
        $validator = Validator::make($request->only(self::FILTERS), [
            'kind' => ['nullable', 'in:'.implode(',', self::KINDS)],
            'canvas_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'integer' => __('audit_log.error.choose'),
            'in' => __('audit_log.error.choose'),
            'date_format' => __('audit_log.error.date'),
            'to.after_or_equal' => __('audit_log.error.range'),
        ], [
            'kind' => __('notices.filter.kind'),
            'canvas_id' => __('notices.filter.canvas'),
            'from' => __('audit_log.filter.from'),
            'to' => __('audit_log.filter.to'),
        ]);
        $valid = $validator->valid();
        $notices = SystemNotice::query()
            ->when($valid['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($valid['canvas_id'] ?? null, fn ($query, $canvas) => $query->where('canvas_id', $canvas))
            ->when($valid['from'] ?? null, fn ($query, $from) => $query->where('last_occurred_at', '>=', Carbon::parse($from, Format::TIMEZONE)->startOfDay()))
            ->when($valid['to'] ?? null, fn ($query, $to) => $query->where('last_occurred_at', '<', Carbon::parse($to, Format::TIMEZONE)->addDay()->startOfDay()))
            ->orderByDesc('last_occurred_at')->orderByDesc('id')
            ->get();
        $canvases = Canvas::query()->whereIn('id', $notices->pluck('canvas_id')->filter())->pluck('name', 'id');

        return Inertia::render('Admin/SystemNotices', [
            'notices' => $notices->map(fn (SystemNotice $notice) => $this->present($notice, $canvases[$notice->canvas_id] ?? null))->values(),
            'total' => $notices->count(),
            'filters' => $request->only(self::FILTERS),
            'filterErrors' => array_map(fn ($messages) => $messages[0], $validator->errors()->messages()),
            'options' => [
                'kinds' => self::KINDS,
                'canvases' => Canvas::query()->whereIn('id', SystemNotice::query()->whereNotNull('canvas_id')->select('canvas_id'))->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function seen(Request $request, SystemNotice $notice): RedirectResponse
    {
        if ($notice->seen_at === null) {
            $notice->forceFill(['seen_at' => now(), 'seen_by' => $request->user()->getKey()])->save();
        }

        return back();
    }

    /** @return array<string, mixed> */
    private function present(SystemNotice $notice, ?string $canvas): array
    {
        $instant = $notice->last_occurred_at->toIso8601String();

        return [
            'id' => $notice->id,
            'kind' => $notice->kind,
            'canvas' => $canvas,
            'details' => $notice->details ?? [],
            'count' => $notice->details['count'] ?? $notice->occurrences,
            'date' => Format::formatDate($instant),
            'last_time' => Format::formatTime($instant),
            'seen' => $notice->seen_at !== null,
        ];
    }
}
