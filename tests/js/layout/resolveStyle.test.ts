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
        expect(resolveStyle('steering', 'domain', { fill: '#000000', text_colour: '#FF0000' }).textColour).toBe('#FF0000');
    });

    it('draws Activities with a hairline border and Domains without', () => {
        expect(resolveStyle('primary_activities', 'activity').border).toBe('#DCDFE2');
        expect(resolveStyle('primary_activities', 'domain').border).toBeNull();
    });

    it('applies every stored style key over the Default colours', () => {
        const style = resolveStyle('steering', 'domain', { fill: '#112233', fill_opacity: 50, border_colour: '#445566', border_width: 3, border_style: 'dashed', corner_radius: 20, font: 'inter', font_size: 20, font_weight: 'normal', italic: true, align_horizontal: 'center', align_vertical: 'bottom' });
        expect(style).toMatchObject({ fill: '#112233', fillOpacity: 50, border: '#445566', borderColour: '#445566', borderWidth: 3, borderStyle: 'dashed', radius: 20, lineHeight: 28, alignHorizontal: 'center', alignVertical: 'bottom' });
        expect(style.font).toEqual({ family: 'Inter', size: 20, weight: 400, italic: true });
    });

    it('reports the Default border, width and alignment when nothing is set', () => {
        expect(resolveStyle('primary_activities', 'activity')).toMatchObject({ borderWidth: 1, borderStyle: 'solid', alignHorizontal: 'left', alignVertical: 'top', fillOpacity: 100 });
        expect(resolveStyle('primary_activities', 'domain')).toMatchObject({ border: null, borderWidth: 0, borderColour: '#DCDFE2' });
    });

    it('hides the border at width 0 and ignores italic for Space Grotesk', () => {
        expect(resolveStyle('primary_activities', 'activity', { border_width: 0 }).border).toBeNull();
        expect(resolveStyle('primary_activities', 'domain', { border_width: 2 }).border).toBe('#DCDFE2');
        expect(resolveStyle('steering', 'domain', { font: 'space_grotesk', italic: true }).font.italic).toBe(false);
    });

    it('picks the automatic text colour from the fill seen through its opacity', () => {
        expect(resolveStyle('steering', 'domain', { fill: '#000000', fill_opacity: 10 }).textColour).toBe('#171B21');
    });
});
