import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import CanvasView from '../../../resources/js/canvas/CanvasView.vue';
import OverlaySwitch from '../../../resources/js/canvas/OverlaySwitch.vue';
import { overlayQuery } from '../../../resources/js/canvas/overlay/query';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { eightAreas, element, emptyInput } from '../layout/support';

const elements = [element('d', 'primary_activities', 'domain', 'Onderwijs')];
const input = { ...emptyInput(), elements };

const render = (slots: Record<string, unknown> = {}): Promise<string> =>
    renderToString(createSSRApp({ render: () => h(CanvasView, { drawn: computeCanvasLayout(input, fakeMeasurer), areas: eightAreas, elements, statements: [], modalities: [] }, slots) }));

describe('CanvasView card slot', () => {
    it('draws the slot content inside a layer that keeps the place and size of its card', async () => {
        const html = await render({ card: ({ block }: { block: { id: string } }) => (block.id === 'd' ? h('i', { 'data-slot': block.id }, 'inhoud') : null) });
        expect(html).toContain('data-slot="d"');
        expect(html).toMatch(/data-overlay-card="d"[^>]*pointer-events-none|pointer-events-none[^>]*data-overlay-card="d"/);
        expect(html).toContain('data-block="d"');
    });

    it('draws no layer without slot content', async () => {
        expect(await render()).not.toContain('data-overlay-card');
    });
});

describe('OverlaySwitch', () => {
    it('offers Geen, Modaliteit and Financieel and selects the current overlay', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(OverlaySwitch, { modelValue: 'financial' }) }));
        expect(html).toContain('Overlay');
        for (const label of ['Geen', 'Modaliteit', 'Financieel']) expect(html).toContain(label);
        expect(html).toMatch(/<option value="financial" selected/);
    });
});

describe('overlayQuery', () => {
    it('keeps the other parameters, drops empty ones and the element', () => {
        expect(overlayQuery('?year=2026&element=x&owner=3', { overlay: 'financial', owner: null })).toEqual({ year: '2026', overlay: 'financial' });
    });
});
