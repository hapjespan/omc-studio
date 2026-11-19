import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import CanvasView from '../../../resources/js/canvas/CanvasView.vue';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { eightAreas, element, emptyInput } from '../layout/support';

const elements = [
    element('d', 'primary_activities', 'domain', 'Onderwijs'),
    element('s', 'supporting_activities', 'domain', 'Beheer'),
    element('a', 'primary_activities', 'activity', 'Les geven', 'd', 1, { modality: 'A' }),
];
const statements = [{ kind: 'mission' as const, title: null, body: 'Leren voor het leven', position: 1 }];
const modalities = [{ key: 'adaptive', letter: 'A', colour: '#1E5FBF' }];
const input = { ...emptyInput(), elements, statements };

const render = (extra: Record<string, unknown> = {}): Promise<string> =>
    renderToString(createSSRApp({ render: () => h(CanvasView, { drawn: computeCanvasLayout(input, fakeMeasurer), areas: eightAreas, elements, statements, modalities, ...extra }) }));

describe('CanvasView', () => {
    it('draws one box per block on the 1680 wide Artboard in the Default colours', async () => {
        const html = await render();
        expect(html).toContain('width:1680px');
        for (const key of eightAreas.map((area) => area.key)) expect(html).toContain(`data-block="${key}"`);
        expect(html).toContain('data-block="d"');
        expect(html).toContain('background:#F6E6CC');
        expect(html).toContain('background:#D9E9F2');
    });

    it('puts the Direction text in the band with the label colour', async () => {
        const html = await render();
        expect(html).toContain('Leren voor het leven');
        expect(html).toContain('Missie');
        expect(html).toContain('color:#C9CED6');
    });

    it('marks the Modality of an Activity with its letter and colour', async () => {
        const html = await render();
        expect(html).toMatch(/background:#1E5FBF;"[^>]*>A</);
    });
});

describe('CanvasView zoom', () => {
    it('scales the Artboard and its scroll size with the zoom', async () => {
        const html = await render({ zoom: 50 });
        expect(html).toContain('width:840px;height:594px');
        expect(html).toContain('transform:scale(0.5)');
    });
});
