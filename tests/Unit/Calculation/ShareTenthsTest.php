<?php

namespace Tests\Unit\Calculation;

use App\Calculation\CalculationInput;
use App\Calculation\FigureCalculator;
use PHPUnit\Framework\TestCase;

class ShareTenthsTest extends TestCase
{
    public function test_a_share_of_12_345_percent_is_12_3_with_one_decimal_and_12_35_with_two(): void
    {
        $elements = [
            ['id' => 'a1', 'parentId' => null, 'areaKey' => 'primary_activities', 'kind' => 'domain', 'modalityKey' => null, 'isUnknownAllocation' => false],
            ['id' => 'u', 'parentId' => null, 'areaKey' => 'supporting_activities', 'kind' => 'domain', 'modalityKey' => null, 'isUnknownAllocation' => true],
        ];
        $areas = [['key' => 'primary_activities', 'position' => 7, 'carriesCosts' => true], ['key' => 'supporting_activities', 'position' => 8, 'carriesCosts' => true]];
        $base = ['costKind' => 'personnel', 'ownerId' => null, 'applicationId' => null, 'forecastCents' => null, 'runBp' => null, 'changeBp' => null, 'fteHundredths' => null];
        $records = [['id' => 'r1', 'budgetCents' => 12345] + $base, ['id' => 'r2', 'budgetCents' => 87655] + $base];
        $sets = ['r1' => [['elementId' => 'a1', 'basisPoints' => 10000]]];

        $figures = (new FigureCalculator)->calculate(new CalculationInput($elements, $areas, $records, $sets));

        $this->assertSame(1235, $figures->elements['a1']['shareBp']);
        $this->assertSame(123, $figures->elements['a1']['shareTenths']);
    }
}
