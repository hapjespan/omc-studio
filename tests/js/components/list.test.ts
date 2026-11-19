import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ListView, { type ListState } from '../../../resources/js/components/list/ListView.vue';

const render = (props: Record<string, unknown>) => renderToString(createSSRApp({ render: () => h(ListView, props) }));
const idle: ListState = { search: '', sort: null, direction: 'asc', filters: {}, archived: false };
const columns = [{ key: 'name', label: 'Naam', sortable: true }, { key: 'email', label: 'E-mail' }];
const rows = [{ id: 1, name: 'Anna', email: 'a@example.test' }, { id: 2, name: 'Bram', email: 'b@example.test' }];

describe('ListView rows, search and sort', () => {
    it('labels the search field and announces the count', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 12, query: idle });

        expect(html).toContain('>Zoeken</label>');
        expect(html).toContain('role="status"');
        expect(html).toContain('12 resultaten');
    });

    it('uses the singular for one result', async () => {
        expect(await render({ title: 'Gebruikers', columns, rows: [rows[0]], count: 1, query: idle })).toContain('1 resultaat<');
    });

    it('makes sortable headers buttons and unsortable ones plain text', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle });

        expect(html).toMatch(/<th[^>]*scope="col"[^>]*><button[^>]*>Naam/);
        expect(html).toMatch(/<th[^>]*scope="col"[^>]*>E-mail<\/th>/);
    });

    it('announces the direction on the sorted header only', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: { ...idle, sort: 'name', direction: 'desc' } });

        expect(html).toContain('aria-sort="descending"');
        expect(html).toContain('aflopend gesorteerd');
        expect(html).not.toContain('aria-sort="ascending"');
    });

    it('renders every row with its cells', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle });

        expect(html).toContain('Anna');
        expect(html).toContain('b@example.test');
    });
});
