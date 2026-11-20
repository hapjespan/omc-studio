import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { resolveStyle } from '../../../resources/js/layout/resolveStyle';
import type { ElementStyle } from '../../../resources/js/layout';
import { backdropOf } from '../../../resources/js/layout/backdrop';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';

const canvas = computeCanvasLayout(withElements([element('d', 'primary_activities', 'domain', 'Domein'), element('a', 'primary_activities', 'activity', 'Activiteit', 'd', 1)]), fakeMeasurer);
const blocks = new Map(canvas.blocks.map((block) => [block.id, block]));
const styled = (styles: Record<string, ElementStyle>) => (id: string) => resolveStyle(blocks.get(id)!.areaKey, blocks.get(id)!.kind, styles[id]);

describe('backdropOf', () => {
    it('is the own fill of the Element at full opacity', () => {
        expect(backdropOf(blocks.get('d')!, blocks, styled({ d: { fill: '#112233' } }))).toBe('#112233');
    });

    it('composites a translucent fill over the fill behind it', () => {
        expect(backdropOf(blocks.get('a')!, blocks, styled({ d: { fill: '#000000' }, a: { fill: '#FFFFFF', fill_opacity: 50 } }))).toBe('#808080');
    });

    it('shows through a fill of 0 opacity to the parent', () => {
        expect(backdropOf(blocks.get('a')!, blocks, styled({ d: { fill: '#112233' }, a: { fill_opacity: 0 } }))).toBe('#112233');
    });
});
