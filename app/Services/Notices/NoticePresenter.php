<?php

namespace App\Services\Notices;

use App\Calculation\Measures;
use App\Models\Application;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Owner;
use App\Models\SystemNotice;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NoticePresenter
{
    /** @var Collection<int|string, string> */
    private Collection $canvases;

    /** @var Collection<int|string, string> */
    private Collection $elements;

    /** @var Collection<int|string, string> */
    private Collection $owners;

    /** @var Collection<int|string, string> */
    private Collection $applications;

    /**
     * @param  iterable<SystemNotice>  $notices
     * @return list<array<string, mixed>>
     */
    public function present(iterable $notices): array
    {
        $notices = collect($notices);
        $this->load($notices);

        return $notices->map(fn (SystemNotice $notice) => $this->one($notice))->values()->all();
    }

    /** @param Collection<int, SystemNotice> $notices */
    private function load(Collection $notices): void
    {
        $details = $notices->map(fn (SystemNotice $notice) => (array) ($notice->details ?? []));
        $subjects = $details->map(fn (array $detail) => $this->elementOf((string) ($detail['check'] ?? '')))->filter()->unique()->all();
        $filters = $details->map(fn (array $detail) => (array) ($detail['filter'] ?? []));

        $this->canvases = Canvas::query()->whereIn('id', $notices->pluck('canvas_id')->filter())->pluck('name', 'id');
        $this->elements = $subjects === [] ? collect() : Element::query()->whereIn('id', $subjects)->pluck('name', 'id');
        $this->owners = Owner::query()->whereIn('id', $filters->pluck('ownerId')->filter()->unique())->pluck('name', 'id');
        $this->applications = Application::query()->whereIn('id', $filters->pluck('applicationId')->filter()->unique())->pluck('name', 'id');
    }

    private function elementOf(string $check): ?string
    {
        $parts = explode('.', $check);

        return $parts[0] === 'total' && Str::isUuid($parts[2] ?? '') ? $parts[2] : null;
    }

    /** @return array<string, mixed> */
    private function one(SystemNotice $notice): array
    {
        $instant = $notice->last_occurred_at->toIso8601String();

        return [
            'id' => $notice->id,
            'kind' => $notice->kind,
            'canvas' => $this->canvases[$notice->canvas_id] ?? null,
            'details' => $this->details($notice->details ?? []),
            'count' => $notice->details['count'] ?? $notice->occurrences,
            'date' => Format::formatDate($instant),
            'last_time' => Format::formatTime($instant),
            'seen' => $notice->seen_at !== null,
        ];
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
            'total' => $this->elements[$parts[2] ?? ''] ?? ($parts[2] ?? ''),
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
                'ownerId' => $this->owners[$value] ?? $value,
                'applicationId' => $this->applications[$value] ?? $value,
                default => $value,
            };
            $pairs[] = __('notices.filter_key.'.$key).': '.$text;
        }

        return $pairs === [] ? __('notices.no_filter') : implode(', ', $pairs);
    }
}
