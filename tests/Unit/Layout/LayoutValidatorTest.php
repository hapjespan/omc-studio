<?php

namespace Tests\Unit\Layout;

use App\Services\Layout\LayoutValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LayoutValidatorTest extends TestCase
{
    private function validator(): LayoutValidator
    {
        return new LayoutValidator([
            ['id' => 'd1', 'area' => 'primary_activities', 'parent' => null, 'kind' => 'domain', 'name' => 'Zorg'],
            ['id' => 'c1', 'area' => 'primary_activities', 'parent' => 'd1', 'kind' => 'cluster', 'name' => 'Keten'],
            ['id' => 'a1', 'area' => 'primary_activities', 'parent' => 'c1', 'kind' => 'activity', 'name' => 'Intake'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $elements
     * @param  array<string, mixed>  $areas
     * @return array<string, mixed>
     */
    private function payload(array $elements = [], array $areas = []): array
    {
        return [
            'structure_version' => 3,
            'layout_version' => 7,
            'format_version' => 1,
            'areas' => $areas,
            'elements' => array_replace([
                'd1' => ['mode' => 'automatic'],
                'c1' => ['mode' => 'automatic'],
                'a1' => ['mode' => 'automatic'],
            ], $elements),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function manual(array $values = []): array
    {
        return array_replace(['mode' => 'manual', 'x' => 0, 'y' => 0, 'width' => 200, 'height' => 100, 'z' => 0, 'style' => []], $values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function area(array $values = []): array
    {
        return array_replace(['x' => 560, 'y' => 400, 'width' => 900, 'height' => 380, 'z' => 0, 'style' => []], $values);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function codes(array $payload): array
    {
        return array_column($this->validator()->refusals($payload), 'rule');
    }

    public function test_a_complete_layout_is_accepted(): void
    {
        $payload = $this->payload(['a1' => $this->manual(['width' => 200])], ['primary_activities' => $this->area()]);

        $this->assertSame([], $this->validator()->refusals($payload));
    }

    public function test_an_element_outside_the_canvas_is_refused(): void
    {
        $payload = $this->payload(['other' => ['mode' => 'automatic']]);

        $this->assertSame(['unknown_element'], $this->codes($payload));
    }

    public function test_a_missing_element_is_refused(): void
    {
        $payload = $this->payload();
        unset($payload['elements']['a1']);

        $refusals = $this->validator()->refusals($payload);

        $this->assertSame(['missing_element'], array_column($refusals, 'rule'));
        $this->assertSame('a1', $refusals[0]['element']);
        $this->assertStringContainsString('Intake', $refusals[0]['message']);
    }

    public function test_a_parent_other_than_the_structure_is_refused(): void
    {
        $payload = $this->payload(['a1' => $this->manual(['parent' => 'd1'])]);

        $this->assertSame(['parent_mismatch'], $this->codes($payload));
    }

    public function test_the_structural_parent_in_the_payload_is_accepted(): void
    {
        $payload = $this->payload(['a1' => $this->manual(['parent' => 'c1']), 'd1' => $this->manual(['parent' => null, 'width' => 400])]);

        $this->assertSame([], $this->codes($payload));
    }

    public function test_a_child_outside_its_parent_width_is_refused(): void
    {
        $refusals = $this->validator()->refusals($this->payload(['a1' => $this->manual(['width' => 300])]));

        $this->assertSame(['outside_parent'], array_column($refusals, 'rule'));
        $this->assertStringContainsString('Intake', $refusals[0]['message']);
        $this->assertStringContainsString('Keten', $refusals[0]['message']);

        $this->assertSame(['outside_parent'], $this->codes($this->payload(['a1' => $this->manual(['x' => -1])])));
        $this->assertSame(['outside_parent'], $this->codes($this->payload(['a1' => $this->manual(['y' => -1])])));
    }

    public function test_a_manual_parent_width_bounds_its_children(): void
    {
        $fitting = $this->payload(['d1' => $this->manual(['width' => 600]), 'c1' => $this->manual(['width' => 400]), 'a1' => $this->manual(['width' => 380])]);
        $this->assertSame([], $this->codes($fitting));

        $sticking = $this->payload(['d1' => $this->manual(['width' => 600]), 'c1' => $this->manual(['width' => 400]), 'a1' => $this->manual(['width' => 420])]);
        $this->assertSame(['outside_parent'], $this->codes($sticking));
    }

    public function test_a_manual_area_width_bounds_its_elements(): void
    {
        $this->assertSame(['outside_parent'], $this->codes($this->payload(['d1' => $this->manual(['width' => 950])])));

        $widened = $this->payload(['d1' => $this->manual(['width' => 950])], ['primary_activities' => $this->area(['width' => 1000])]);
        $this->assertSame([], $this->codes($widened));
    }

    public function test_an_area_outside_the_artboard_is_refused(): void
    {
        $this->assertSame(['area_outside'], $this->codes($this->payload([], ['primary_activities' => $this->area(['y' => 100])])));
        $this->assertSame(['area_outside'], $this->codes($this->payload([], ['primary_activities' => $this->area(['x' => -1])])));
        $this->assertSame(['area_outside'], $this->codes($this->payload([], ['primary_activities' => $this->area(['x' => 800, 'width' => 900])])));
        $this->assertSame(['area_outside'], $this->codes($this->payload([], ['primary_activities' => $this->area(['y' => 1000, 'height' => 200])])));
    }

    public function test_the_direction_band_takes_no_layout(): void
    {
        $this->assertSame(['direction_layout'], $this->codes($this->payload([], ['direction' => $this->area()])));
    }

    public function test_an_unknown_area_key_is_refused(): void
    {
        $this->assertSame(['unknown_area'], $this->codes($this->payload([], ['elsewhere' => $this->area()])));
    }

    public function test_a_size_below_the_minimum_is_refused(): void
    {
        $this->assertSame(['too_small'], $this->codes($this->payload(['a1' => $this->manual(['width' => 39])])));
        $this->assertSame(['too_small'], $this->codes($this->payload(['a1' => $this->manual(['height' => 23])])));
        $this->assertSame(['too_small'], $this->codes($this->payload([], ['primary_activities' => $this->area(['height' => 23])])));
        $this->assertSame([], $this->codes($this->payload(['a1' => $this->manual(['width' => 40, 'height' => 24])])));
    }

    public function test_a_width_below_the_children_is_refused(): void
    {
        $payload = $this->payload(['c1' => $this->manual(['width' => 100]), 'a1' => $this->manual(['x' => 10, 'width' => 120])]);

        $this->assertContains('outside_parent', $this->codes($payload));
    }

    public function test_a_style_outside_the_allowed_keys_is_refused(): void
    {
        $payload = $this->payload(['a1' => $this->manual(['style' => ['fill' => 'rood']])]);

        $refusals = $this->validator()->refusals($payload);

        $this->assertSame(['style'], array_column($refusals, 'rule'));
        $this->assertStringContainsString('Intake', $refusals[0]['message']);
    }

    public function test_a_manual_element_without_a_size_is_refused(): void
    {
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => ['mode' => 'manual', 'x' => 0, 'y' => 0]])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => ['mode' => 'drawn']])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload([], ['primary_activities' => ['x' => 0]])));
    }

    public function test_values_beyond_the_column_range_are_refused(): void
    {
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => $this->manual(['y' => 3000000000])])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => $this->manual(['height' => PHP_INT_MAX])])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => $this->manual(['z' => PHP_INT_MAX])])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload([], ['primary_activities' => $this->area(['z' => -3000000000])])));
    }

    public function test_unknown_keys_on_an_entry_are_refused(): void
    {
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => $this->manual(['format_version' => 999])])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => $this->manual(['element_id' => 'other'])])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload(['a1' => ['mode' => 'automatic', 'x' => -5]])));
        $this->assertSame(['invalid_layout'], $this->codes($this->payload([], ['primary_activities' => $this->area(['canvas_id' => 2])])));
    }

    public function test_asserting_a_refused_layout_throws_on_the_rule_key(): void
    {
        $payload = $this->payload(['a1' => $this->manual(['width' => 39])]);

        try {
            $this->validator()->assertValid($payload);
            $this->fail('The refused layout was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['rule'], array_keys($exception->errors()));
            $this->assertStringContainsString('Intake', $exception->errors()['rule'][0]);
        }
    }

    public function test_asserting_a_valid_layout_passes(): void
    {
        $this->validator()->assertValid($this->payload(['a1' => $this->manual()]));

        $this->expectNotToPerformAssertions();
    }
}
