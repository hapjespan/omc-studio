import { describe, expect, it } from 'vitest';
import fixture from '../../fixtures/automatic-widths.json';
import { automaticWidths } from '../../../resources/js/layout/automaticWidths';
import type { AreaKey } from '../../../resources/js/layout/types';

describe('automaticWidths', () => {
    it.each(fixture.cases)('$name', ({ elements, expected, ...overrides }) => {
        const { areaWidths, elementWidths } = overrides as { areaWidths?: Partial<Record<AreaKey, number>>; elementWidths?: Record<string, number> };
        expect(automaticWidths(elements as never, { areas: areaWidths, elements: elementWidths })).toEqual(expected);
    });
});
