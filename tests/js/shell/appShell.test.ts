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

const render = async () => (await renderToString(createSSRApp({ render: () => h(AppShell, null, { default: () => h('h1', 'Overzicht') }) }))).replace(/<!--.*?-->/g, '');

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

    it('shows the application name as a link to the overview and the role as a menu button', async () => {
        const html = await render();

        expect(html).toMatch(/<a href="\/"[^>]*>OMC Studio<\/a>/);
        expect(html).toContain('Administrator');
        expect(html).not.toContain('Kruimelpad');
    });

    it('shows the breadcrumb with the organisation linking to the overview when a canvas is open', async () => {
        page.props.canvas = { id: 7, name: 'Strategie', organisation: { id: 3, name: 'Acme' } };
        const html = await render();
        delete page.props.canvas;

        expect(html).toContain('aria-label="Kruimelpad"');
        expect(html).toMatch(/<a href="\/"[^>]*>Acme<\/a>/);
        expect(html).toContain('Strategie');
    });
});
