import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { matchesName } from '../../../resources/js/components/list/ConfirmDelete.vue';
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

describe('ListView filters', () => {
    const filters = [{ name: 'role', label: 'Rol', options: [{ value: 'admin', label: 'Beheerder' }, { value: 'reader', label: 'Lezer' }] }];

    it('offers a labelled select per filter', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, filters });

        expect(html).toContain('>Rol</label>');
        expect(html).toContain('<option value="reader"');
    });

    it('shows an active filter as a chip button naming the filter', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 1, query: { ...idle, filters: { role: 'admin' } }, filters });

        expect(html).toMatch(/<button[^>]*>Rol: Beheerder<span class="sr-only">/);
        expect(html).toContain('Filter verwijderen: Rol Beheerder');
    });

    it('shows no chips without an active filter', async () => {
        expect(await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, filters })).not.toContain('Filter verwijderen');
    });
});

describe('ListView form flow', () => {
    const withForm = { form: () => h('p', 'formulier') };
    const renderWith = (props: Record<string, unknown>, slots: Record<string, () => unknown>) => renderToString(createSSRApp({ render: () => h(ListView, props, slots) }));

    it('offers Nieuw and opens the first cell as a button when a form is provided', async () => {
        const html = await renderWith({ title: 'Gebruikers', columns, rows, count: 2, query: idle }, withForm);

        expect(html).toMatch(/>\s*Nieuw\s*<\/button>/);
        expect(html).toMatch(/<td[^>]*><button[^>]*>Anna<\/button>/);
    });

    it('offers neither without a form or when creating is not allowed', async () => {
        const plain = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle });
        const readOnly = await renderWith({ title: 'Gebruikers', columns, rows, count: 2, query: idle, canCreate: false }, withForm);

        expect(plain).not.toContain('Nieuw');
        expect(readOnly).not.toContain('Nieuw');
    });
});

describe('matchesName', () => {
    it('is exact, case sensitive and ignores nothing but surrounding spaces', () => {
        expect(matchesName('Intake', 'Intake')).toBe(true);
        expect(matchesName(' Intake ', 'Intake')).toBe(true);
        expect(matchesName('intake', 'Intake')).toBe(false);
        expect(matchesName('', '')).toBe(false);
    });
});

describe('ListView deletion', () => {
    it('labels each delete button with the row name', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, canDelete: true });

        expect(html).toContain('aria-label="Verwijderen Anna"');
        expect(html).toContain('aria-label="Verwijderen Bram"');
    });

    it('offers no delete button unless allowed', async () => {
        expect(await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle })).not.toContain('Verwijderen');
    });
});
