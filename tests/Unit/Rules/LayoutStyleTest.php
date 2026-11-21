<?php

namespace Tests\Unit\Rules;

use App\Http\Requests\SaveLayoutRequest;
use App\Rules\LayoutStyle;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LayoutStyleTest extends TestCase
{
    /** @return list<string> */
    private function violations(mixed $style): array
    {
        return (new LayoutStyle('Kosten'))->violations($style);
    }

    public function test_every_allowed_key_and_value_passes(): void
    {
        $style = ['fill' => '#F6E6CC', 'fill_opacity' => 100, 'border_colour' => '#171b21', 'border_width' => 8, 'border_style' => 'dashed',
            'corner_radius' => 64, 'font' => 'space_grotesk', 'font_size' => 48, 'font_weight' => 'bold', 'italic' => true,
            'text_colour' => '#171B21', 'align_horizontal' => 'center', 'align_vertical' => 'middle'];

        $this->assertSame([], $this->violations($style));
        $this->assertSame([], $this->violations([]));
    }

    public function test_a_colour_outside_the_form_is_refused_with_the_field_and_the_fix(): void
    {
        $this->assertSame(['Vulling Kosten: gebruik de vorm #RRGGBB'], $this->violations(['fill' => '#12345']));
        $this->assertSame(['Vulling Kosten: gebruik de vorm #RRGGBB'], $this->violations(['fill' => 'rood']));
        $this->assertSame(['Rand Kosten: gebruik de vorm #RRGGBB'], $this->violations(['border_colour' => '#GGGGGG']));
        $this->assertSame(['Tekst Kosten: gebruik de vorm #RRGGBB'], $this->violations(['text_colour' => 12]));
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function outOfRange(): array
    {
        return [
            'opacity low' => ['fill_opacity', -1], 'opacity high' => ['fill_opacity', 101], 'opacity float' => ['fill_opacity', 50.5],
            'border width' => ['border_width', 9], 'radius' => ['corner_radius', 65], 'size low' => ['font_size', 9], 'size high' => ['font_size', 49],
            'size string' => ['font_size', '14'], 'border style' => ['border_style', 'dotted'], 'font' => ['font', 'Inter'],
            'weight' => ['font_weight', 'light'], 'italic' => ['italic', 'yes'], 'horizontal' => ['align_horizontal', 'middle'], 'vertical' => ['align_vertical', 'left'],
        ];
    }

    #[DataProvider('outOfRange')]
    public function test_a_value_outside_its_range_is_refused(string $key, mixed $value): void
    {
        $this->assertCount(1, $this->violations([$key => $value]));
    }

    public function test_an_unknown_key_or_a_non_array_is_refused(): void
    {
        $this->assertSame(['Opmaak Kosten: onbekend onderdeel shadow'], $this->violations(['shadow' => 3]));
        $this->assertSame(['Opmaak Kosten: de opmaak is ongeldig'], $this->violations('fill'));
    }

    public function test_the_rule_fails_a_validator_run(): void
    {
        $validator = Validator::make(['style' => ['fill' => 'rood']], ['style' => [new LayoutStyle('Kosten')]]);

        $this->assertSame(['Vulling Kosten: gebruik de vorm #RRGGBB'], $validator->errors()->get('style'));
    }

    public function test_the_request_asks_for_the_versions_and_both_collections(): void
    {
        $rules = (new SaveLayoutRequest)->rules();

        $this->assertSame([], Validator::make(['structure_version' => 1, 'layout_version' => 0, 'format_version' => 1, 'areas' => [], 'elements' => []], $rules)->errors()->keys());
        $this->assertEqualsCanonicalizing(['structure_version', 'layout_version', 'format_version', 'areas', 'elements'], Validator::make([], $rules)->errors()->keys());
        $this->assertSame(['format_version'], Validator::make(['structure_version' => 1, 'layout_version' => 0, 'format_version' => 2, 'areas' => [], 'elements' => []], $rules)->errors()->keys());
    }
}
