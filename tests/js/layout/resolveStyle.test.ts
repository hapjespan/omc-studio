import { describe, expect, it } from 'vitest';
import defaults from '../../../resources/data/default-colours.json';
import { contrastRatio } from '../../../resources/js/colour';
import { resolveStyle } from '../../../resources/js/layout/resolveStyle';
import type { AreaKey } from '../../../resources/js/layout/types';

const activityAreas: AreaKey[] = ['steering', 'governing', 'primary_activities', 'supporting_activities'];

describe('resolveStyle Area heading', () => {
    it.each(activityAreas)('draws the %s heading in secondary text with letter spacing at the contract contrast', (area) => {
        const style = resolveStyle(area, 'area');
        expect(style.textColour).toBe('#454C57');
        expect(style.font.tracking).toBe(0.04);
        expect(contrastRatio(style.fill!, style.textColour)).toBeGreaterThanOrEqual(4.5);
    });

    it('leaves the Direction band heading without letter spacing', () => {
        expect(resolveStyle('direction', 'area').font.tracking).toBeUndefined();
    });
});

describe('resolveStyle', () => {
    it.each(activityAreas)('uses the Default colours of %s at every level', (area) => {
        const levels = defaults.areas[area];
        expect(resolveStyle(area, 'domain').fill).toBe(levels.domain);
        expect(resolveStyle(area, 'cluster').fill).toBe(levels.cluster);
        expect(resolveStyle(area, 'activity').fill).toBe(levels.activity);
    });

    it.each(activityAreas)('gives text of at least 4.5:1 on every fill in %s', (area) => {
        for (const level of ['domain', 'cluster', 'activity'] as const) {
            const style = resolveStyle(area, level);
            expect(contrastRatio(style.fill!, style.textColour)).toBeGreaterThanOrEqual(4.5);
        }
    });

    it('draws the Direction band in ink with white text and #C9CED6 labels', () => {
        const style = resolveStyle('direction', 'area');
        expect(style.fill).toBe('#171B21');
        expect(style.textColour).toBe('#FFFFFF');
        expect(style.labelColour).toBe('#C9CED6');
    });

    it('has no fill and ink text in the list Areas', () => {
        const group = resolveStyle('actors', 'domain');
        expect(group.fill).toBeNull();
        expect(group.textColour).toBe('#171B21');
        expect(group.font.weight).toBe(600);
        expect(resolveStyle('actors', 'activity').font.weight).toBe(400);
    });

    it('lets an Element style win and picks ink or white by contrast', () => {
        expect(resolveStyle('steering', 'domain', { fill: '#000000' })).toMatchObject({ fill: '#000000', textColour: '#FFFFFF' });
        expect(resolveStyle('steering', 'domain', { fill: '#FFFF00' }).textColour).toBe('#171B21');
        expect(resolveStyle('steering', 'domain', { fill: '#000000', textColour: '#FF0000' }).textColour).toBe('#FF0000');
    });

    it('draws Activities with a hairline border and Domains without', () => {
        expect(resolveStyle('primary_activities', 'activity').border).toBe('#DCDFE2');
        expect(resolveStyle('primary_activities', 'domain').border).toBeNull();
    });
});
