import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import Miniature from '../../../resources/js/canvas/Miniature.vue';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { buildMiniature } from '../../../resources/js/layout/miniature';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';

const drawn = computeCanvasLayout(
    withElements([
        element('d', 'primary_activities', 'domain', 'Onderwijs'),
        element('a', 'primary_activities', 'activity', 'Les', 'd'),
        element('g', 'actors', 'domain', 'Klanten'),
    ]),
    fakeMeasurer,
);

describe('buildMiniature', () => {
    it('reduces the eight Areas and the coloured Domains, in their colours', () => {
        const { rects, width, height } = buildMiniature(drawn, 240);
        expect([width, height]).toEqual([240, 169.71]);
        expect(rects.filter((rect) => ['direction', 'steering', 'governing', 'actors', 'channels', 'value_propositions', 'primary_activities', 'supporting_activities'].includes(rect.id))).toHaveLength(8);
        expect(rects.find((rect) => rect.id === 'd')?.fill).toBe('#F6E6CC');
        expect(rects.find((rect) => rect.id === 'direction')?.fill).toBe('#171B21');
        expect(rects.some((rect) => rect.id === 'a' || rect.id === 'g')).toBe(false);
    });

    it('scales positions with the width', () => {
        const small = buildMiniature(drawn, 120).rects.find((rect) => rect.id === 'd')!;
        const large = buildMiniature(drawn, 240).rects.find((rect) => rect.id === 'd')!;
        expect(large.x).toBeCloseTo(small.x * 2, 1);
    });
});

describe('Miniature', () => {
    it('is hidden from assistive technology and holds no text', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(Miniature, { canvas: drawn }) }));
        expect(html).toContain('aria-hidden="true"');
        expect(html).toContain('focusable="false"');
        expect(html).not.toContain('<text');
        expect(html).not.toContain('tabindex');
    });
});
