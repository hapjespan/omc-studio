<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EvidenceDocumentsTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    /** @return list<string> */
    private function rows(string $document, string $criterion): array
    {
        return array_values(array_filter(explode("\n", $this->read($document)), fn (string $line) => str_starts_with($line, '| '.$criterion.' ')));
    }

    public function test_no_document_calls_the_sc026_journey_not_built_while_its_spec_exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/tests/e2e/dt-new-element-journey.spec.ts');

        foreach (['docs/review-evidence.md', 'specs/001-omc-studio-v1/dt-results.md'] as $document) {
            $this->assertNotEmpty($this->rows($document, 'SC-026'), $document);

            foreach ($this->rows($document, 'SC-026') as $row) {
                $this->assertStringNotContainsString('not built', $row);
                $this->assertStringContainsString('dt-new-element-journey.spec.ts', $row);
            }
        }

        $this->assertStringNotContainsString('not built, the parts are proven separately', $this->read('docs/review-evidence.md'));
    }

    public function test_sc001_has_one_status_in_both_documents(): void
    {
        $evidence = $this->rows('docs/review-evidence.md', 'SC-001')[0];
        $acceptance = $this->rows('specs/001-omc-studio-v1/d1-acceptance.md', 'SC-001')[0];

        $this->assertStringContainsString('not run', $evidence);
        $this->assertStringContainsString('niet gedraaid', $acceptance);
        $this->assertStringNotContainsString('met on the development host', $evidence);
    }

    public function test_the_open_points_do_not_list_the_fixed_new_domain_overlap(): void
    {
        $document = $this->read('specs/001-omc-studio-v1/d2-d3-dt-acceptance.md');
        $heading = strpos($document, '## Wat nog openstaat');

        $this->assertNotFalse($heading);

        $open = substr($document, $heading);

        $this->assertStringContainsString('ef13083', $open);
        $this->assertStringNotContainsString('moet de PO beslissen', $open);
    }

    public function test_sc025_names_the_field_journey_and_the_mouse_drag_separately(): void
    {
        $results = $this->rows('specs/001-omc-studio-v1/dt-results.md', 'SC-025 place and size');
        $this->assertCount(1, $results);
        $this->assertStringContainsString('dt-journey.spec.ts', $results[0]);

        $evidence = $this->rows('docs/review-evidence.md', 'SC-025 Domain dragged,');
        $this->assertCount(1, $evidence);
        $this->assertStringContainsString('dt-journey.spec.ts', explode(' | ', $evidence[0])[1]);

        foreach (['docs/review-evidence.md', 'specs/001-omc-studio-v1/dt-results.md'] as $document) {
            $this->assertStringContainsString('drawing-colour-drag.spec.ts', implode("\n", $this->rows($document, 'SC-025')), $document);
        }
    }
}
