import { describe, expect, it } from 'vitest';
import { printWarning } from '../../../resources/js/canvas/printCanvas';

describe('printCanvas', () => {
    it('warns only when blocks fall beyond the Artboard', () => {
        expect(printWarning(0)).toBe('');
        expect(printWarning(3)).toBe('Niet alles past op het A3-tekenvlak: 3 blokken komen op een volgende pagina');
    });
});

describe('ExportMenu overlay switch', () => {
    it('offers the Met overlay switch off by default', async () => {
        const { createSSRApp, h } = await import('vue');
        const { renderToString } = await import('vue/server-renderer');
        const { default: ExportMenu } = await import('../../../resources/js/canvas/ExportMenu.vue');
        const html = await renderToString(createSSRApp({ render: () => h(ExportMenu, { canvasId: 1, organisationId: null }) }));
        expect(html).toContain('Met overlay');
        expect(html).toMatch(/role="switch"/);
        expect(html).not.toContain('checked');
    });
});
