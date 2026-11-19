import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ArtboardEdge from '../../../resources/js/canvas/ArtboardEdge.vue';
import OverflowNotice from '../../../resources/js/canvas/OverflowNotice.vue';
import { contentHeight } from '../../../resources/js/canvas/extent';
import { message } from '../../../resources/js/components/feedback/status';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element, withElements } from '../layout/support';

const render = (component: object, props: Record<string, unknown>): Promise<string> => renderToString(createSSRApp({ render: () => h(component, props) }));

describe('ArtboardEdge', () => {
    it('always draws the edge with its label and the hatched area only when content passes it', async () => {
        const fits = await render(ArtboardEdge, { height: 1188 });
        expect(fits).toContain('data-artboard-edge');
        expect(fits).toContain('Rand A3');
        expect(fits).not.toContain('data-overflow-area');
        const spills = await render(ArtboardEdge, { height: 1500 });
        expect(spills).toContain('data-overflow-area');
        expect(spills).toContain('top:1188px;width:1680px;height:312px');
    });
});

describe('OverflowNotice', () => {
    it('says how many blocks fall outside and announces it', async () => {
        message.value = '';
        const html = await render(OverflowNotice, { count: 3 });
        expect(html).toContain('Niet alles past op het A3-tekenvlak: 3 blokken vallen erbuiten');
        await Promise.resolve();
        expect(message.value).toBe('Niet alles past op het A3-tekenvlak: 3 blokken vallen erbuiten');
    });

    it('shows nothing when everything fits', async () => {
        message.value = '';
        expect(await render(OverflowNotice, { count: 0 })).not.toContain('data-overflow-notice');
        expect(message.value).toBe('');
    });
});

describe('contentHeight', () => {
    it('grows past the Artboard with the content and never shrinks below it', () => {
        expect(contentHeight(computeCanvasLayout(withElements([]), fakeMeasurer))).toBe(1188);
        const many = withElements(Array.from({ length: 40 }, (_, index) => element(`d${index}`, 'primary_activities', 'domain', `Domein ${index}`, null, index)));
        expect(contentHeight(computeCanvasLayout(many, fakeMeasurer))).toBeGreaterThan(1188);
    });
});
