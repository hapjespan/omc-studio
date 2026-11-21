<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class ReviewEvidenceTest extends TestCase
{
    public function test_every_test_the_evidence_file_names_exists(): void
    {
        $text = (string) file_get_contents(base_path('docs/review-evidence.md'));
        $missing = [];

        preg_match_all('/`(tests\/[A-Za-z0-9_\/.-]+\.(?:php|ts))(?:::(test_\w+))?`(?:, `::(test_\w+)`)*/', $text, $found, PREG_SET_ORDER);

        foreach ($found as $match) {
            if (! is_file(base_path($match[1]))) {
                $missing[] = $match[1];
            }
        }

        preg_match_all('/`(tests\/[A-Za-z0-9_\/.-]+\.php)::(test_\w+)`((?:, `::test_\w+`)*)/', $text, $chains, PREG_SET_ORDER);

        foreach ($chains as $chain) {
            preg_match_all('/test_\w+/', $chain[2].$chain[3], $names);

            foreach ($names[0] as $name) {
                if (is_file(base_path($chain[1])) && ! str_contains((string) file_get_contents(base_path($chain[1])), 'function '.$name.'(')) {
                    $missing[] = $chain[1].'::'.$name;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    private const UNCITED = ['007', '044', '047', '049', '052', '053', '054', '055', '058', '062', '063', '064', '066', '069', '070', '075'];

    /** @return list<string> */
    private function missingRows(string $text): array
    {
        $missing = [];

        foreach (self::UNCITED as $number) {
            preg_match('/^\| FR-'.$number.'\b[^\n]*`tests\/[^`\n]+`/m', $text, $row) === 1 || $missing[] = 'FR-'.$number;
        }

        return $missing;
    }

    public function test_the_table_of_the_sixteen_uncited_requirements_is_complete(): void
    {
        $this->assertSame([], $this->missingRows((string) file_get_contents(base_path('docs/review-evidence.md'))));
    }

    public function test_the_completeness_check_fails_on_a_copy_without_the_table_or_with_a_lost_row(): void
    {
        $text = (string) file_get_contents(base_path('docs/review-evidence.md'));
        $without = (string) preg_replace('/^## 10\. .*\z/ms', '', $text);
        $lost = (string) preg_replace('/^\| FR-058\b[^\n]*\n/m', '', $text);

        $this->assertCount(16, $this->missingRows($without));
        $this->assertSame(['FR-058'], $this->missingRows($lost));
    }
}
