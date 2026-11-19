import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';

const page = { props: { auth: { role: 'administrator' }, appName: 'OMC Studio' } as Record<string, unknown>, url: '/' };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: { post: () => {}, visit: () => {} },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    Head: { render: () => null },
}));

const AppShell = (await import('../../../resources/js/shell/AppShell.vue')).default;

const render = () => renderToString(createSSRApp({ render: () => h(AppShell, null, { default: () => h('h1', 'Overzicht') }) }));

describe('AppShell', () => {
    it('puts the skip link first, before every landmark', async () => {
        const html = await render();

        expect(html.indexOf('Naar hoofdinhoud')).toBeGreaterThan(-1);
        expect(html.indexOf('Naar hoofdinhoud')).toBeLessThan(html.indexOf('<header'));
        expect(html).toContain('href="#main"');
    });

    it('renders the four landmarks with one main holding the page', async () => {
        const html = await render();

        expect(html).toContain('<header');
        expect(html.match(/<main/g)).toHaveLength(1);
        expect(html).toContain('id="main"');
        expect(html).toContain('aria-label="Linkerpaneel"');
        expect(html.indexOf('<main')).toBeLessThan(html.indexOf('Overzicht</h1>'));
    });
});
