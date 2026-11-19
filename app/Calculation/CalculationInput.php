<?php

namespace App\Calculation;

/**
 * @phpstan-type ElementRow array{id: string|int, parentId: string|int|null, areaKey: string, kind: string, modalityKey: string|int|null, isUnknownAllocation: bool}
 * @phpstan-type AreaRow array{key: string, position: int, carriesCosts: bool}
 * @phpstan-type CostRecordRow array{id: string|int, costKind: string, ownerId: string|int|null, applicationId: string|int|null, budgetCents: int, forecastCents: int|null, runBp: int|null, changeBp: int|null, fteHundredths: int|null}
 * @phpstan-type SetLine array{elementId: string|int, basisPoints: int}
 */
final readonly class CalculationInput
{
    /**
     * @param  list<ElementRow>  $elements
     * @param  list<AreaRow>  $areas
     * @param  list<CostRecordRow>  $costRecords
     * @param  array<string|int, list<SetLine>>  $sets
     */
    public function __construct(
        public array $elements,
        public array $areas,
        public array $costRecords,
        public array $sets,
    ) {}
}
