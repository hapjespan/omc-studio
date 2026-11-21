<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class DateRangeFilter
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'date_format' => __('audit_log.error.date'),
            'to.after_or_equal' => __('audit_log.error.range'),
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return ['from' => __('audit_log.filter.from'), 'to' => __('audit_log.filter.to')];
    }

    public static function start(string $day): Carbon
    {
        return Carbon::parse($day, Format::TIMEZONE)->startOfDay()->utc();
    }

    public static function end(string $day): Carbon
    {
        return Carbon::parse($day, Format::TIMEZONE)->addDay()->startOfDay()->utc();
    }
}
