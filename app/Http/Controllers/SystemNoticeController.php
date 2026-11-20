<?php

namespace App\Http\Controllers;

use App\Calculation\Measures;
use App\Models\Application;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Owner;
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
            ->when($valid['from'] ?? null, fn ($query, $from) => $query->where('last_occurred_at', '>=', Carbon::parse($from, Format::TIMEZONE)->startOfDay()->utc()))
            ->when($valid['to'] ?? null, fn ($query, $to) => $query->where('last_occurred_at', '<', Carbon::parse($to, Format::TIMEZONE)->addDay()->startOfDay()->utc()))
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

    public function seen(SystemNotice $notice): RedirectResponse
    {
        if ($notice->seen_at === null) {
            $notice->forceFill(['seen_at' => now()])->save();
        }

        return back();
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function details(array $details): array
    {
        if (isset($details['check'])) {
            $details['check'] = $this->check((string) $details['check']);
        }
        if (array_key_exists('filter', $details)) {
            $details['filter'] = $this->filter((array) $details['filter']);
        }

        return $details;
    }

    private function check(string $check): string
    {
        $parts = explode('.', $check);
        $head = $parts[0] === 'breakdown' ? 'breakdown.'.($parts[1] ?? '') : $parts[0];
        $name = $parts[0] === 'total' ? ($parts[1] ?? '') : end($parts);
        $measure = in_array($name, Measures::NAMES, true) ? __('notices.measure.'.$name) : '';
        $subject = match ($parts[0]) {
            'total' => Element::query()->whereKey($parts[2] ?? null)->value('name') ?? ($parts[2] ?? ''),
            'area' => __('areas.'.($parts[1] ?? '').'.name'),
            default => '',
        };
        $key = 'notices.check.'.$head;

        return $key === __($key) ? $check : __($key, ['measure' => $measure, 'subject' => $subject]);
    }

    /** @param  array<string, mixed>  $filter */
    private function filter(array $filter): string
    {
        $pairs = [];
        foreach ($filter as $key => $value) {
            $text = match ($key) {
                'costKind' => __('cost_kind.'.$value),
                'ownerId' => Owner::query()->whereKey($value)->value('name') ?? $value,
                'applicationId' => Application::query()->whereKey($value)->value('name') ?? $value,
                default => $value,
            };
            $pairs[] = __('notices.filter_key.'.$key).': '.$text;
        }

        return $pairs === [] ? __('notices.no_filter') : implode(', ', $pairs);
    }

    /** @return array<string, mixed> */
    private function present(SystemNotice $notice, ?string $canvas): array
    {
        $instant = $notice->last_occurred_at->toIso8601String();

        return [
            'id' => $notice->id,
            'kind' => $notice->kind,
            'canvas' => $canvas,
            'details' => $this->details($notice->details ?? []),
            'count' => $notice->details['count'] ?? $notice->occurrences,
            'date' => Format::formatDate($instant),
            'last_time' => Format::formatTime($instant),
            'seen' => $notice->seen_at !== null,
        ];
    }
}
