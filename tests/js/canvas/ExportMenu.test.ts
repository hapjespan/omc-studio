import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ExportMenu from '../../../resources/js/canvas/ExportMenu.vue';

const render = (overlay?: string): Promise<string> => renderToString(createSSRApp({ render: () => h(ExportMenu, { canvasId: 1, organisationId: null, overlay }) }));

describe('ExportMenu overlay switch', () => {
    it('offers the Met overlay switch on when an overlay is active', async () => {
        const html = await render('financial');
        expect(html).toContain('Met overlay');
        expect(html).toMatch(/role="switch"/);
        expect(html).toContain('checked');
    });

    it('hides the switch when there is no overlay', async () => {
        expect(await render('none')).not.toContain('Met overlay');
        expect(await render()).not.toContain('Met overlay');
    });
});
