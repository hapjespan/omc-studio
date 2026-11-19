<?php

namespace Tests\Unit\Support;

use App\Support\CanvasFont;
use PHPUnit\Framework\TestCase;

class CanvasFontTest extends TestCase
{
    public function test_the_list_is_inter_and_space_grotesk(): void
    {
        $this->assertSame(['Inter', 'Space Grotesk'], array_map(fn (CanvasFont $font) => $font->value, CanvasFont::cases()));
    }

    public function test_every_face_is_vendored_and_declared(): void
    {
        $root = dirname(__DIR__, 3);
        $css = (string) file_get_contents("$root/resources/css/fonts.css");

        foreach (CanvasFont::cases() as $font) {
            foreach ($font->files() as $file) {
                $this->assertFileExists("$root/public/fonts/$file");
                $this->assertStringContainsString("/fonts/$file", $css);
            }
        }
    }
}
