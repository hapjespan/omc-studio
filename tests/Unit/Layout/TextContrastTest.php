<?php

namespace Tests\Unit\Layout;

use App\Services\Layout\LayoutValidator;
use Tests\TestCase;

class TextContrastTest extends TestCase
{
    private function validator(): LayoutValidator
    {
        return new LayoutValidator([
            ['id' => 'd', 'area' => 'primary_activities', 'parent' => null, 'kind' => 'domain', 'name' => 'Inkoop'],
            ['id' => 'a', 'area' => 'primary_activities', 'parent' => 'd', 'kind' => 'activity', 'name' => 'Bestellen'],
        ]);
    }

    /** @param array<string, mixed> $style
     * @param array<string, mixed> $parentStyle
     * @return list<string> */
    private function messages(array $style, array $parentStyle = []): array
    {
        $payload = ['elements' => [
            'd' => ['mode' => 'manual', 'x' => 0, 'y' => 0, 'width' => 400, 'height' => 100, 'z' => 0, 'style' => $parentStyle],
            'a' => ['mode' => 'manual', 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 40, 'z' => 1, 'style' => $style],
        ], 'areas' => []];

        return array_column($this->validator()->refusals($payload), 'message');
    }

    public function test_a_text_colour_below_the_ratio_is_refused_with_the_measured_ratio(): void
    {
        $this->assertSame(['Tekst Bestellen: kies een tekstkleur met meer contrast, nu 1,0:1, minstens 4,5:1'], $this->messages(['fill' => '#FFFFFF', 'text_colour' => '#FFFFFF']));
    }

    public function test_the_ratio_shown_is_never_rounded_up_to_the_limit(): void
    {
        $messages = $this->messages(['fill' => '#777777', 'text_colour' => '#FFFFFF']);

        $this->assertStringContainsString('nu 4,4:1', $messages[0]);
    }

    public function test_enough_contrast_passes(): void
    {
        $this->assertSame([], $this->messages(['fill' => '#FFFFFF', 'text_colour' => '#171B21']));
    }

    public function test_opacity_is_composited_over_the_parent_fill(): void
    {
        $this->assertSame([], $this->messages(['fill' => '#000000', 'fill_opacity' => 0, 'text_colour' => '#171B21'], ['fill' => '#FFFFFF']));
        $this->assertCount(1, $this->messages(['fill' => '#000000', 'fill_opacity' => 0, 'text_colour' => '#171B21'], ['fill' => '#171B21']));
    }

    public function test_without_a_fill_the_default_fill_of_the_kind_applies(): void
    {
        $this->assertCount(1, $this->messages(['text_colour' => '#FFFFFF']));
    }

    public function test_an_automatic_text_colour_is_not_checked(): void
    {
        $this->assertSame([], $this->messages(['fill' => '#171B21']));
    }
}
