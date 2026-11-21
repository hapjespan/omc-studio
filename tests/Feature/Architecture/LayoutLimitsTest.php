<?php

namespace Tests\Feature\Architecture;

use App\Support\LayoutLimits;
use Tests\TestCase;

class LayoutLimitsTest extends TestCase
{
    private const FILES = [
        'app/Services/Layout/Artboard.php',
        'app/Services/Layout/LayoutValidator.php',
        'app/Services/Layout/MovedBlockFit.php',
        'app/Services/Layout/AutomaticWidths.php',
        'app/Rules/LayoutStyle.php',
        'resources/js/edit-engine/constraints.ts',
        'resources/js/layout/arrangeAreas.ts',
        'resources/js/layout/direction.ts',
        'resources/js/pages/Drawing/StylePanel.vue',
        'resources/js/pages/Drawing/TextStylePanel.vue',
    ];

    private const LITERALS = [
        '/\b1680\b/', '/\b1188\b/', '/MIN_WIDTH = 40/', '/MIN_HEIGHT = 24/', '/DIRECTION_HEIGHT = 180/',
        '/\[0, 100\]/', '/\[0, 8\]/', '/\[0, 64\]/', '/\[10, 48\]/', '/high: 100/', '/high: 8\b/', '/high: 64/', '/number < 10 \|\| number > 48/',
    ];

    public function test_no_listed_file_holds_a_literal_copy_of_a_layout_limit(): void
    {
        $copies = [];

        foreach (self::FILES as $file) {
            $source = (string) file_get_contents(base_path($file));

            foreach (self::LITERALS as $pattern) {
                preg_match($pattern, $source) === 1 && $copies[] = $file.' '.$pattern;
            }
        }

        $this->assertSame([], $copies);
    }

    public function test_the_php_side_reads_the_shared_file(): void
    {
        $json = json_decode((string) file_get_contents(base_path('resources/data/layout-limits.json')), true);

        $this->assertSame($json['artboard'], ['width' => LayoutLimits::artboardWidth(), 'height' => LayoutLimits::artboardHeight()]);
        $this->assertSame([$json['minWidth'], $json['minHeight'], $json['directionHeight']], [LayoutLimits::minWidth(), LayoutLimits::minHeight(), LayoutLimits::directionHeight()]);
        $this->assertSame($json['style'], LayoutLimits::style());
    }
}
