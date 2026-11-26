# Contract: calculation module (D2)

Namespace `App\Calculation`. Pure: no database, no clock, no logging, no exceptions for business outcomes (FR-059). Same input, same output. ADR 0028.

## Entry point

`FigureCalculator::calculate(CalculationInput $input): Figures`

## Input (value objects, built by the caller)

- `elements`: list of `{id, parentId|null, areaKey, kind, modalityKey|null, isUnknownAllocation}` for every active Element of the Canvas. Archived Elements carry no lines (FR-039); a line whose Element is not in the input is counted on no Element, so the Budget check of FR-068 fails with its difference rather than throwing.
- `areas`: list of `{key, position, carriesCosts}`.
- `costRecords`: list of `{id, costKind, ownerId, applicationId|null, budgetCents, forecastCents|null, runBp|null, changeBp|null, fteHundredths|null}` for the included Cost records of FR-060, already filtered by the caller on Cost kind, Owner and Application.
- `sets`: map of Cost record id to ordered list of `{elementId, basisPoints}`; a Cost record absent from the map has no set.

## Output

- `elements[id]`: Direct and Total for Budget, Forecast, Run, Change, Undetermined (cents); Share in basis points of the Canvas total, or null when the Canvas total is 0; FTE numerator (hundredths times basis points); `contributions`: per Cost record the amount and the basis points allocated within the subtree.
- `areas[key]`: Total per measure.
- `canvasTotal` per measure; `allocatedTotal` and `unknownTotal` with counts (side panel).
- `breakdowns`: per Cost kind, per Modality plus `none` and `unknown_allocation`, and Run, Change, Undetermined.
- `closingCheck`: `passed` and a list of failures `{check, differenceCents}`; checks of FR-068.

Finalise (FR-071) and Snapshots (FR-079) call it without a filter. Bekijken receives Element and Area figures and the side panel; `contributions` of one Element are sent only on request (hover, focus or selection) through a partial reload, never for all Elements at once (P-1).

When `closingCheck.passed` is false the caller shows no figure and records a System notice (FR-068, US21.10); the module itself records nothing.

## Rules it implements

FR-061 to FR-068, SD-5, SD-6, SD-18, SD-19. Largest remainder: `LargestRemainder::split(int $total, list<int> $weights): list<int>`, ties to the earlier index; shared fixture `tests/fixtures/largest-remainder.json` with the TypeScript mirror (R-4).

## Tests at this seam

Unit tests per FR-093; property test per FR-095 (1000 cases, seeded, seed printed on failure); a test with an injected one-cent discrepancy that proves `passed` is false (US21.10).
