<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Models\SystemNotice;
use App\Services\Notices\NoticePresenter;
use App\Services\Notices\SystemNoticeQuery;
use App\Support\DateRangeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class SystemNoticeController extends Controller
{
    private const FILTERS = ['kind', 'canvas_id', 'from', 'to'];

    private const KINDS = ['closing_check_failed', 'snapshot_damaged', 'repeated_lockouts'];

    public function index(Request $request, SystemNoticeQuery $query, NoticePresenter $presenter): Response
    {
        $validator = Validator::make($request->only(self::FILTERS), [
            'kind' => ['nullable', 'in:'.implode(',', self::KINDS)],
            'canvas_id' => ['nullable', 'integer'],
            ...DateRangeFilter::rules(),
        ], [
            'integer' => __('audit_log.error.choose'),
            'in' => __('audit_log.error.choose'),
            ...DateRangeFilter::messages(),
        ], [
            'kind' => __('notices.filter.kind'),
            'canvas_id' => __('notices.filter.canvas'),
            ...DateRangeFilter::attributes(),
        ]);
        $notices = $query->get($validator->valid());

        return Inertia::render('Admin/SystemNotices', [
            'notices' => $presenter->present($notices),
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
}
