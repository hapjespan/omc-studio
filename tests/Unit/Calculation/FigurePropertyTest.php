<?php

namespace Tests\Unit\Calculation;

use App\Calculation\CalculationInput;
use App\Calculation\FigureCalculator;
use PHPUnit\Framework\TestCase;

class FigurePropertyTest extends TestCase
{
    private const CASES = 1000;

    private const MAX_CENTS = 99999999999;

    /** @return list<int> */
    private function basisPoints(int $lines): array
    {
        $cuts = [];
        while (count($cuts) < $lines - 1) {
            $cuts[mt_rand(1, 9999)] = true;
        }
        $cuts = array_keys($cuts);
        sort($cuts);
        $points = [];
        $previous = 0;
        foreach ([...$cuts, 10000] as $cut) {
            $points[] = $cut - $previous;
            $previous = $cut;
        }

        return $points;
    }

    private function cents(): int
    {
        return match (mt_rand(0, 3)) {
            0 => 0,
            1 => mt_rand(0, 100),
            2 => self::MAX_CENTS - mt_rand(0, 100),
            default => mt_rand(0, self::MAX_CENTS),
        };
    }

    public function test_the_closing_check_passes_for_random_trees_budgets_and_sets(): void
    {
        $seed = (int) (getenv('PROPERTY_SEED') ?: random_int(1, PHP_INT_MAX >> 1));
        mt_srand($seed);
        $calculator = new FigureCalculator;

        for ($case = 0; $case < self::CASES; $case++) {
            $elements = [['id' => 'u', 'parentId' => null, 'areaKey' => 'supporting_activities', 'kind' => 'domain', 'modalityKey' => null, 'isUnknownAllocation' => true]];
            $ids = [];
            $domains = mt_rand(1, 4);
            for ($d = 0; $d < $domains; $d++) {
                $elements[] = ['id' => "d$d", 'parentId' => null, 'areaKey' => mt_rand(0, 1) ? 'primary_activities' : 'supporting_activities', 'kind' => 'domain', 'modalityKey' => mt_rand(0, 1) ? 'm'.mt_rand(1, 3) : null, 'isUnknownAllocation' => false];
                $ids[] = "d$d";
                $clusterId = null;
                if (mt_rand(0, 1)) {
                    $clusterId = "c$d";
                    $elements[] = ['id' => $clusterId, 'parentId' => "d$d", 'areaKey' => $elements[count($elements) - 1]['areaKey'], 'kind' => 'cluster', 'modalityKey' => null, 'isUnknownAllocation' => false];
                    $ids[] = $clusterId;
                }
                for ($a = mt_rand(0, 4); $a > 0; $a--) {
                    $elements[] = ['id' => "a$d-$a", 'parentId' => $clusterId ?? "d$d", 'areaKey' => $elements[count($elements) - 1]['areaKey'], 'kind' => 'activity', 'modalityKey' => mt_rand(0, 1) ? 'm'.mt_rand(1, 3) : null, 'isUnknownAllocation' => false];
                    $ids[] = "a$d-$a";
                }
            }

            $records = [];
            $sets = [];
            for ($r = mt_rand(0, 12); $r > 0; $r--) {
                $run = mt_rand(0, 10000);
                $change = mt_rand(0, 10000 - $run);
                $records[] = [
                    'id' => "r$r", 'costKind' => 'k'.mt_rand(1, 3), 'ownerId' => null, 'applicationId' => null,
                    'budgetCents' => $this->cents(), 'forecastCents' => mt_rand(0, 2) ? $this->cents() : null,
                    'runBp' => mt_rand(0, 3) ? $run : null, 'changeBp' => mt_rand(0, 3) ? $change : null, 'fteHundredths' => mt_rand(0, 1) ? mt_rand(0, 5000) : null,
                ];
                if (mt_rand(0, 3)) {
                    $count = mt_rand(1, min(20, count($ids)));
                    $targets = array_rand(array_flip($ids), $count);
                    $targets = (array) $targets;
                    shuffle($targets);
                    $sets["r$r"] = array_map(fn (string $id, int $bp) => ['elementId' => $id, 'basisPoints' => $bp], $targets, $this->basisPoints(count($targets)));
                }
            }

            $areas = [['key' => 'primary_activities', 'position' => 7, 'carriesCosts' => true], ['key' => 'supporting_activities', 'position' => 8, 'carriesCosts' => true]];
            $figures = $calculator->calculate(new CalculationInput($elements, $areas, $records, $sets));

            $this->assertTrue($figures->closingCheck['passed'], sprintf('seed %d case %d: %s', $seed, $case, json_encode($figures->closingCheck['failures'])));
        }
    }
}
