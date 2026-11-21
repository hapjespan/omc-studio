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

    it('stacks the layers above every drawn block', async () => {
        const withChild = [element('d', 'primary_activities', 'domain', 'Onderwijs'), element('a', 'primary_activities', 'activity', 'Les geven', 'd')];
        const html = await renderToString(createSSRApp({ render: () => h(CanvasView, { drawn: computeCanvasLayout({ ...emptyInput(), elements: withChild }, fakeMeasurer), areas: eightAreas, elements: withChild, statements: [], modalities: [], layersAbove: true }, { card: () => h('i', 'inhoud') }) }));
        const layers = [...html.matchAll(/z-index:\s*(\d+)[^"]*"[^>]*data-overlay-card|data-overlay-card[^>]*style="[^"]*z-index:\s*(\d+)/g)].map((match) => Number(match[1] ?? match[2]));
        const blocks = [...html.matchAll(/data-block="[^"]*"[^>]*style="[^"]*z-index:\s*(\d+)|style="[^"]*z-index:\s*(\d+)[^"]*"[^>]*data-block=/g)].map((match) => Number(match[1] ?? match[2]));
        expect(layers.length).toBeGreaterThan(0);
        expect(blocks.length).toBeGreaterThan(0);
        expect(Math.min(...layers)).toBeGreaterThan(Math.max(...blocks));
    });

    it('draws no layer without slot content', async () => {
        expect(await render()).not.toContain('data-overlay-card');
    });
});

describe('OverlaySwitch', () => {
    it('offers Geen, Modaliteit and Financieel as a segmented control and marks the current overlay', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(OverlaySwitch, { modelValue: 'financial' }) }));
        expect(html).toContain('Overlay');
        for (const label of ['Geen', 'Modaliteit', 'Financieel']) expect(html).toContain(label);
        expect(html).toMatch(/aria-pressed="true"[^>]*>Financieel</);
        expect(html.match(/aria-pressed="false"/g)).toHaveLength(2);
    });
});

describe('overlayQuery', () => {
    it('keeps the other parameters, drops empty ones and the element', () => {
        expect(overlayQuery('?year=2026&element=x&owner=3', { overlay: 'financial', owner: null })).toEqual({ year: '2026', overlay: 'financial' });
    });
});

describe('CanvasView overlay accessibility', () => {
    const withChild = [element('d', 'primary_activities', 'domain', 'Onderwijs'), element('a', 'primary_activities', 'activity', 'Les geven', 'd')];
    const html = (extra: Record<string, unknown>): Promise<string> =>
        renderToString(createSSRApp({ render: () => h(CanvasView, { drawn: computeCanvasLayout({ ...emptyInput(), elements: withChild }, fakeMeasurer), areas: eightAreas, elements: withChild, statements: [], modalities: [], ...extra }) }));

    it('makes covered children inert so pointer and keyboard act on the Domain', async () => {
        const output = await html({ inertIds: new Set(['a']) });
        expect(/<button[^>]*data-block="a"[^>]*>/.exec(output)![0]).toContain('inert');
        expect(/<button[^>]*data-block="d"[^>]*>/.exec(output)![0]).not.toContain('inert');
    });

    it('puts the Area total into the Area label', async () => {
        const output = await html({ figureLines: (id: string) => (id === 'primary_activities' ? ['Totaal € 1.235 · 12,4%'] : []) });
        expect(output).toContain('aria-label="Primaire activiteiten. Totaal € 1.235 · 12,4%"');
    });
});
