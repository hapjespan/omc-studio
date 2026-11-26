<?php

namespace App\Calculation;

final class LargestRemainder
{
    /**
     * @param  list<int>  $weights
     * @return list<int>
     */
    public static function split(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum === 0) {
            return array_fill(0, count($weights), 0);
        }

        $parts = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $parts[$index] = intdiv($total * $weight, $sum);
            $remainders[$index] = ($total * $weight) % $sum;
        }

        $order = array_keys($weights);
        usort($order, fn (int $a, int $b) => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        foreach (array_slice($order, 0, $total - array_sum($parts)) as $index) {
            $parts[$index]++;
        }

        return $parts;
    }
}
