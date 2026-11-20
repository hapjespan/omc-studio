<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class D1RefusalCoverageTest extends TestCase
{
    private const REFUSALS = [
        'users.last_administrator',
        'users.email_taken',
        'users.email_invalid',
        'users.role_invalid',
        'users.current_password_required',
        'users.name_required',
        'organisations.delete_blocked',
        'organisations.name_taken',
        'organisations.name_required',
        'organisations.logo_invalid',
        'templates.name_taken',
        'canvas_lifecycle.name_required',
        'quick_entry.activity_parent',
        'quick_entry.duplicate',
        'quick_entry.indent_mismatch',
        'quick_entry.no_clusters',
        'quick_entry.too_long',
        'direction_entry.title_required',
        'direction_entry.title_too_long',
        'direction_entry.too_long',
        'modality_entry.rationale_needs_modality',
        'modality_entry.list_area',
        'validation.name_forbidden_sign',
        'validation.text_max',
        'list.invalid_filters',
        'list.invalid_query',
        'list.invalid_sort',
    ];

    public function test_every_d1_refusal_message_is_named_by_a_server_test(): void
    {
        $messages = json_decode(File::get(lang_path('nl.json')), true);
        $keys = array_merge(self::REFUSALS, array_filter(array_keys($messages), fn (string $key) => str_starts_with($key, 'rule.')));
        $corpus = $this->corpus();

        $untested = [];
        foreach (array_unique($keys) as $key) {
            $this->assertArrayHasKey($key, $messages, "refusal {$key} is not in nl.json");
            if (! $this->covered($key, $messages[$key], $corpus)) {
                $untested[] = $key;
            }
        }

        $this->assertSame([], $untested, 'refusal messages without a server test');
    }

    public function test_a_refusal_without_a_test_is_caught(): void
    {
        $this->assertFalse($this->covered('rule.planted_refusal', 'Een weigering die niemand test', $this->corpus()));
    }

    public function test_every_listed_refusal_is_recorded_in_the_refusal_list(): void
    {
        $list = File::get(base_path('specs/001-omc-studio-v1/d1-refusals.md'));
        $messages = json_decode(File::get(lang_path('nl.json')), true);

        $missing = array_values(array_filter(
            array_merge(self::REFUSALS, array_filter(array_keys($messages), fn (string $key) => str_starts_with($key, 'rule.'))),
            fn (string $key) => ! str_contains($list, "`{$key}`"),
        ));

        $this->assertSame([], $missing, 'refusals missing from d1-refusals.md');
    }

    private function corpus(): string
    {
        $self = __FILE__;
        $text = '';
        foreach (['Feature', 'Unit'] as $directory) {
            foreach (File::allFiles(base_path("tests/{$directory}")) as $file) {
                if ($file->getPathname() !== $self && $file->getExtension() === 'php') {
                    $text .= File::get($file->getPathname());
                }
            }
        }

        return $text;
    }

    private function covered(string $key, string $message, string $corpus): bool
    {
        if (str_contains($corpus, "'{$key}'") || str_contains($corpus, "\"{$key}\"")) {
            return true;
        }

        $segments = preg_split('/:[a-z_]+/', $message);
        usort($segments, fn (string $a, string $b) => strlen($b) <=> strlen($a));
        $needle = trim($segments[0], " :\n");

        return strlen($needle) >= 8 && str_contains($corpus, $needle);
    }
}
