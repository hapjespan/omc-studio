import { describe, expect, it } from 'vitest';
import contrast from '../../fixtures/contrast.json';
import defaults from '../../../resources/data/default-colours.json';
import { autoTextColour, contrastRatio, defaultColourPairs, effectiveFill } from '../../../resources/js/colour';

describe('autoTextColour', () => {
    it.each(contrast.cases)('$background gets $text', ({ background, text }) => {
        expect(autoTextColour(background)).toBe(text);
    });
});

describe('effectiveFill', () => {
    it.each(contrast.effectiveCases)('$fill at $opacity over $behind is $effective', ({ fill, opacity, behind, effective, text }) => {
        expect(effectiveFill(fill, opacity, behind)).toBe(effective);
        expect(autoTextColour(effective)).toBe(text);
    });
});

describe('Default colours', () => {
    it('meet the minimum ratio for their text colour', () => {
        const pairs = defaultColourPairs();
        expect(pairs.length).toBeGreaterThan(8);
        for (const { background, text } of pairs) {
            expect(contrastRatio(background, text), `${background} on ${text}`).toBeGreaterThanOrEqual(contrast.minimumRatio);
        }
    });

    it('keep the Direction labels readable on the Direction band', () => {
        expect(contrastRatio(defaults.directionLabels, defaults.areas.direction.domain as string)).toBeGreaterThanOrEqual(contrast.minimumRatio);
    });
});
