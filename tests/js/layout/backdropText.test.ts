import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import type { CanvasLayoutInput } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';

const draw = (input: CanvasLayoutInput) => new Map(computeCanvasLayout(input, fakeMeasurer).blocks.map((block) => [block.id, block]));
const dark = { mode: 'manual' as const, x: 16, y: 48, width: 400, height: 300, z: 1, style: { fill: '#000000' } };

describe('automatic text colour against what lies behind', () => {
    it('is white for items with no fill on a dark Area', () => {
        const input = { ...withElements([element('g', 'actors', 'domain', 'Klanten'), element('i', 'actors', 'activity', 'Ouders', 'g', 1)]), layouts: { actors: { mode: 'manual' as const, x: 0, y: 200, width: 400, height: 300, z: 0, style: { fill: '#000000' } } } };
        const blocks = draw(input);
        expect(blocks.get('g')!.style.textColour).toBe('#FFFFFF');
        expect(blocks.get('i')!.style.textColour).toBe('#FFFFFF');
    });

    it('is white for an Activity with opacity 0 inside a dark Domain', () => {
        const input = { ...withElements([element('d', 'primary_activities', 'domain', 'Domein'), element('a', 'primary_activities', 'activity', 'Activiteit', 'd', 1)]), layouts: { d: dark, a: { mode: 'manual' as const, x: 10, y: 40, width: 200, height: 40, z: 2, style: { fill_opacity: 0 } } } };
        expect(draw(input).get('a')!.style.textColour).toBe('#FFFFFF');
    });

    it('is white for a translucent light fill over a dark Domain', () => {
        const input = { ...withElements([element('d', 'primary_activities', 'domain', 'Domein'), element('a', 'primary_activities', 'activity', 'Activiteit', 'd', 1)]), layouts: { d: dark, a: { mode: 'manual' as const, x: 10, y: 40, width: 200, height: 40, z: 2, style: { fill: '#FFFFFF', fill_opacity: 10 } } } };
        expect(draw(input).get('a')!.style.textColour).toBe('#FFFFFF');
    });
});

describe('Area styles', () => {
    it('are drawn from the stored Layout of the Area', () => {
        const input = { ...withElements([]), layouts: { primary_activities: { mode: 'manual' as const, x: 0, y: 200, width: 600, height: 300, z: 0, style: { fill: '#112233', border_colour: '#445566', border_width: 2 } } } };
        const area = draw(input).get('primary_activities')!;
        expect(area.style).toMatchObject({ fill: '#112233', border: '#445566', borderWidth: 2, textColour: '#FFFFFF' });
    });
});
