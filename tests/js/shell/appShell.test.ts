import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';

const page = { props: { auth: { role: 'administrator' }, appName: 'OMC Studio' } as Record<string, unknown>, url: '/' };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: { post: () => {}, visit: () => {}, on: () => () => {} },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    Head: { render: () => null },
}));

const { shellLayout } = await import('../../../resources/js/shell/layout');
const AppShell = (await import('../../../resources/js/shell/AppShell.vue')).default;

const render = async () => (await renderToString(createSSRApp({ render: () => h(AppShell, null, { default: () => h('h1', 'Overzicht') }) }))).replace(/<!--.*?-->/g, '');

describe('AppShell', () => {
    it('puts the skip link first, before every landmark', async () => {
        const html = await render();

        expect(html.indexOf('Naar hoofdinhoud')).toBeGreaterThan(-1);
        expect(html.indexOf('Naar hoofdinhoud')).toBeLessThan(html.indexOf('<header'));
        expect(html).toContain('href="#main"');
    });

    it('offers a collapsed Menu button that controls the tabs', async () => {
        const html = await render();

        expect(html).toMatch(/<button[^>]*aria-controls="primary-tabs"[^>]*aria-expanded="false"[^>]*>Menu<\/button>/);
        expect(html).toContain('id="primary-tabs"');
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

        expect(html).toMatch(/<a href="\/"[^>]*><span[^>]*><svg[\s\S]*?<\/svg><span>OMC Studio<\/span><\/span><\/a>/);
        expect(html).toContain('Administrator');
        expect(html).not.toContain('Kruimelpad');
    });

    it('shows the breadcrumb with the organisation linking to the overview when a canvas is open', async () => {
        page.props.canvas = { id: 7, name: 'Strategie', kind: 'canvas', archived: false, organisation: { id: 3, name: 'Acme' } };
        const html = await render();
        delete page.props.canvas;

        expect(html).toContain('aria-label="Kruimelpad"');
        expect(html).toMatch(/<a href="\/"[^>]*>Acme<\/a>/);
        expect(html).toContain('Strategie');
    });
});

describe('AppShell with a Canvas', () => {
    it('shows the mode group Modus with one link per allowed mode', async () => {
        page.props.canvas = { id: 7, name: 'Strategie', kind: 'canvas', archived: false, organisation: { id: 3, name: 'Acme' } };
        page.props.auth = { role: 'reader' };
        const html = (await renderToString(createSSRApp({ render: () => h(AppShell) }))).replace(/<!--.*?-->/g, '');
        delete page.props.canvas;
        page.props.auth = { role: 'administrator' };

        expect(html).toContain('role="group" aria-label="Modus"');
        expect(html).toContain('Bekijken');
        expect(html).not.toContain('Invoeren');
    });
});

describe('AppShell layout options', () => {
    it('keeps 16 pixel padding, no header targets and a visible status region by default', async () => {
        const html = await render();

        expect(html).toMatch(/<main[^>]*class="shell-main p-4"/);
        expect(html).not.toContain('page-title');
        expect(html).not.toContain('page-actions');
        expect(html).toMatch(/<div role="status"[^>]*class="text-\[12.5px\] text-secondary"/);
    });

    it('hides the status text visually only when a page asks for it', async () => {
        shellLayout.quietStatus = true;
        const html = await render();
        shellLayout.quietStatus = false;

        expect(html).toMatch(/<div role="status"[^>]*class="sr-only"/);
    });

    it('widens the main padding to 24 pixels only when a page asks for it', async () => {
        shellLayout.widePadding = true;
        const html = await render();
        shellLayout.widePadding = false;

        expect(html).toMatch(/<main[^>]*class="shell-main p-6"/);
    });

    it('renders the header targets only while a page fills them', async () => {
        shellLayout.headerSlots = 1;
        const html = await render();
        shellLayout.headerSlots = 0;

        expect(html).toContain('id="page-title"');
        expect(html).toContain('id="page-actions"');
    });
});
