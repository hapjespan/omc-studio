<?php

namespace App\Calculation;

/**
 * @phpstan-type Money array<string, int>
 * @phpstan-type LineRow array{elementId: string|int, basisPoints: int, amounts: Money}
 * @phpstan-type ElementFigures array{direct: Money, total: Money, shareBp: int|null, shareTenths: int|null, fteNumerator: int, contributions: array<string|int, array{amountCents: int, basisPoints: int}>}
 */
final readonly class Figures
{
    /**
     * @param  array<string|int, ElementFigures>  $elements
     * @param  array<string|int, list<LineRow>>  $lines
     * @param  array<string, Money>  $areas
     * @param  Money  $canvasTotal
     * @param  array{costKind: array<string, Money>, modality: array<string, Money>, split: array{run: int, change: int, undetermined: int}}  $breakdowns
     * @param  array{allocated: array{budget: int, forecast: int, count: int}, unknown: array{budget: int, forecast: int, count: int}}  $sidePanel
     * @param  array{passed: bool, failures: list<array{check: string, differenceCents: int}>}|null  $closingCheck
     */
    public function __construct(
        public array $elements,
        public array $lines,
        public array $areas,
        public array $canvasTotal,
        public array $breakdowns,
        public array $sidePanel,
        public ?array $closingCheck = null,
    ) {}
}
