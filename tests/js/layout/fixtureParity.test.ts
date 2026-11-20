import { describe, expect, it } from 'vitest';
import contrast from '../../fixtures/contrast.json';
import widths from '../../fixtures/automatic-widths.json';
import { autoTextColour, effectiveFill } from '../../../resources/js/colour';
import { automaticWidths } from '../../../resources/js/layout/automaticWidths';

const WIDTH_CASES = 5;
const CONTRAST_CASES = 11;
const EFFECTIVE_CASES = 5;

describe('Shared fixtures', () => {
    it('evaluates every width case and keeps the case count of the PHP twin', () => {
        expect(widths.cases).toHaveLength(WIDTH_CASES);
        for (const { elements, expected, name, ...overrides } of widths.cases) {
            const { areaWidths, elementWidths } = overrides as { areaWidths?: Record<string, number>; elementWidths?: Record<string, number> };
            expect(automaticWidths(elements as never, { areas: areaWidths, elements: elementWidths }), name).toEqual(expected);
        }
    });

    it('evaluates every contrast case and keeps the case count of the PHP twin', () => {
        expect(contrast.cases).toHaveLength(CONTRAST_CASES);
        expect(contrast.effectiveCases).toHaveLength(EFFECTIVE_CASES);
        for (const { background, text } of contrast.cases) expect(autoTextColour(background), background).toBe(text);
        for (const { fill, opacity, behind, effective } of contrast.effectiveCases) expect(effectiveFill(fill, opacity, behind)).toBe(effective);
    });
});
