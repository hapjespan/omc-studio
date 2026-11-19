<?php

namespace App\Calculation;

/**
 * @phpstan-type Money array<string, int>
 * @phpstan-type LineRow array{elementId: string|int, basisPoints: int, amounts: Money}
 * @phpstan-type ElementFigures array{direct: Money}
 */
final readonly class Figures
{
    /**
     * @param  array<string|int, ElementFigures>  $elements
     * @param  array<string|int, list<LineRow>>  $lines
     */
    public function __construct(
        public array $elements,
        public array $lines,
    ) {}
}
