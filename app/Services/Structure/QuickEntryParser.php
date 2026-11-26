<?php

namespace App\Services\Structure;

use App\Models\Element;
use App\Support\FieldLimits;

class QuickEntryParser
{
    private const MAX = FieldLimits::NAME;

    /**
     * @param  array<string, string>  $existing
     * @return array{lines: list<array{line: int, level: int, kind: string, name: string, path: string, status: string}>, errors: list<array{line: int, message: string}>}
     */
    public function parse(string $text, string $areaKind, array $existing): array
    {
        $rows = $this->rows($text);
        $unknown = Element::normalise(__('elements.unknown_allocation'));
        $lines = [];
        $errors = [];
        $seen = [];
        $stack = [];
        $previous = -1;

        foreach ($rows as $index => ['line' => $number, 'level' => $level, 'name' => $name]) {
            $stack = array_slice($stack, 0, $level);
            $stack[$level] = Element::normalise($name);
            $path = implode('>', $stack);
            $message = $this->problem($level, $previous, $name, $areaKind, ($stack[0] ?? null) === $unknown, $path, $seen, $existing);
            $previous = $level;

            if ($message !== null) {
                $errors[] = ['line' => $number, 'message' => __('quick_entry.line_error', ['line' => $number, 'message' => $message])];

                continue;
            }

            $seen[$path] = true;
            $lines[] = [
                'line' => $number,
                'level' => $level,
                'kind' => $this->kind($level, $areaKind, ($rows[$index + 1]['level'] ?? 0) === 2),
                'name' => $name,
                'path' => $path,
                'status' => isset($existing[$path]) ? 'exists' : 'new',
            ];
        }

        return ['lines' => $lines, 'errors' => $errors];
    }

    /**
     * @param  array<string, true>  $seen
     * @param  array<string, string>  $existing
     */
    private function problem(int $level, int $previous, string $name, string $areaKind, bool $underUnknown, string $path, array $seen, array $existing): ?string
    {
        return match (true) {
            $level > 2 || $level > $previous + 1 => __('quick_entry.indent_mismatch'),
            $level === 2 && $areaKind === 'list' => __('quick_entry.no_clusters'),
            $level > 0 && ($existing[substr($path, 0, (int) strrpos($path, '>'))] ?? null) === 'activity' => __('quick_entry.activity_parent'),
            $underUnknown => __('rule.unknown_allocation_managed'),
            str_contains($name, '>') => __('quick_entry.sign'),
            mb_strlen($name) > self::MAX => __('quick_entry.too_long'),
            isset($seen[$path]) => __('quick_entry.duplicate'),
            default => null,
        };
    }

    private function kind(int $level, string $areaKind, bool $clusterFollows): string
    {
        return match (true) {
            $level === 0 => 'domain',
            $level === 1 && $areaKind !== 'list' && $clusterFollows => 'cluster',
            default => 'activity',
        };
    }

    /** @return list<array{line: int, level: int, name: string}> */
    private function rows(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\R/', $text) ?: [] as $index => $raw) {
            $level = 0;
            $rest = rtrim($raw);

            while (true) {
                if (str_starts_with($rest, "\t")) {
                    $rest = substr($rest, 1);
                } elseif (str_starts_with($rest, '  ')) {
                    $rest = substr($rest, 2);
                } else {
                    break;
                }

                $level++;
            }

            if (trim($rest) !== '') {
                $rows[] = ['line' => $index + 1, 'level' => $level, 'name' => trim($rest)];
            }
        }

        return $rows;
    }
}
