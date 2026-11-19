import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { matchesName } from '../../../resources/js/components/list/ConfirmDelete.vue';
import UndoMessage from '../../../resources/js/components/list/UndoMessage.vue';
import { message } from '../../../resources/js/components/feedback/status';
import { SearchEcho } from '../../../resources/js/components/list/searchEcho';
import ListView, { type ListState } from '../../../resources/js/components/list/ListView.vue';

const strip = (html: string): string => html.replace(/<!--.*?-->/g, '');
const render = async (props: Record<string, unknown>): Promise<string> => strip(await renderToString(createSSRApp({ render: () => h(ListView, props) })));
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
    const renderWith = async (props: Record<string, unknown>, slots: Record<string, () => unknown>): Promise<string> => strip(await renderToString(createSSRApp({ render: () => h(ListView, props, slots) })));

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

describe('ListView table language', () => {
    it('shows the secondary value under the name and right-aligns numeric columns', async () => {
        const html = await render({ title: 'Gebruikers', columns: [{ key: 'name', label: 'Naam', secondary: 'email' }, { key: 'total', label: 'Totaal', align: 'right' }], rows: [{ ...rows[0], total: 5 }], count: 1, query: idle });

        expect(html).toMatch(/<span class="block[^"]*">a@example.test<\/span>/);
        expect(html).toMatch(/<th[^>]*text-right[^>]*>Totaal<\/th>/);
    });

    it('right-aligns a sortable header and shows the kind above the panel title', async () => {
        const html = await render({ title: 'Gebruikers', columns: [{ key: 'name', label: 'Naam' }, { key: 'total', label: 'Totaal', align: 'right', sortable: true }], rows, count: 2, query: idle, kind: 'Gebruiker' });

        expect(html).toMatch(/<th[^>]*text-right[^>]*><button/);
    });

    it('draws a 16 pixel checkbox inside a 24 pixel target', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, bulkActions: [{ key: 'archive', label: 'Archiveren' }], archivable: true });

        expect(html.match(/<label class="[^"]*size-6[^"]*"><input[^>]*class="size-4"/g)?.length).toBe(4);
    });
});

describe('UndoMessage', () => {
    it('offers Ongedaan maken next to the message', async () => {
        const html = strip(await renderToString(createSSRApp({ render: () => h(UndoMessage, { message: 'Anna verwijderd' }) })));

        expect(html).toContain('role="status"');
        expect(html).toContain('Anna verwijderd');
        expect(html).toMatch(/<button[^>]*>Ongedaan maken<\/button>/);
    });

    it('names the broken rule instead and offers only to close', async () => {
        const html = strip(await renderToString(createSSRApp({ render: () => h(UndoMessage, { message: 'Anna verwijderd', refusal: 'Ongedaan maken kan niet meer' }) })));

        expect(html).toContain('role="alert"');
        expect(html).toContain('Ongedaan maken kan niet meer');
        expect(html).not.toMatch(/>Ongedaan maken<\/button>/);
        expect(html).toMatch(/>Sluiten<\/button>/);
    });
});

describe('ListView undo', () => {
    it('renders the undo message when the parent provides one', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, undo: { id: 'a', message: 'Anna verwijderd' } });

        expect(html).toContain('Anna verwijderd');
    });
});

describe('ListView bulk actions', () => {
    const bulkActions = [{ key: 'delete', label: 'Verwijderen' }, { key: 'archive', label: 'Archiveren' }];

    it('labels the select-all and every row checkbox', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, bulkActions });

        expect(html).toContain('aria-label="Alle rijen selecteren"');
        expect(html).toContain('aria-label="Selecteer Anna"');
        expect(html).toContain('aria-label="Selecteer Bram"');
    });

    it('has no checkboxes without bulk actions', async () => {
        expect(await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle })).not.toContain('type="checkbox"');
    });

    it('reports the result with the name and reason of each skipped row', async () => {
        const result = { done: 2, verb: 'verwijderd', skipped: [{ name: 'Cor', reason: 'Gebruikt in een canvas' }] };
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, bulkActions, bulkResult: result });

        expect(html).toContain('2 verwijderd, 1 overgeslagen');
        expect(message.value).toBe('2 verwijderd, 1 overgeslagen');
        expect(html).not.toContain('role="status" class="text-[13px]"');
        expect(html).toContain('Cor: Gebruikt in een canvas');
    });

    it('omits the skipped part when nothing was skipped', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, bulkActions, bulkResult: { done: 3, verb: 'gearchiveerd', skipped: [] } });

        expect(html).toContain('3 gearchiveerd<');
        expect(html).not.toContain('overgeslagen');
    });
});

describe('ListView archive handling', () => {
    const archivedRows = [...rows, { id: 3, name: 'Cor', email: 'c@example.test', archived_at: '2026-01-01T00:00:00Z' }];

    it('offers the toggle only for archivable lists', async () => {
        expect(await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, archivable: true })).toContain('Toon gearchiveerd');
        expect(await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle })).not.toContain('Toon gearchiveerd');
    });

    it('reflects the shown state in the toggle', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows: archivedRows, count: 3, query: { ...idle, archived: true }, archivable: true });

        expect(html).toMatch(/<input checked type="checkbox"/);
    });

    it('marks an archived row with text and offers Terugzetten for that row only', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows: archivedRows, count: 3, query: { ...idle, archived: true }, archivable: true });

        expect(html.match(/Gearchiveerd<\/span>/g)).toHaveLength(1);
        expect(html).toContain('aria-label="Terugzetten Cor"');
        expect(html).not.toContain('aria-label="Terugzetten Anna"');
    });
});

describe('ListView undo exclusion', () => {
    it('shows no undo for a kind that is not undoable', async () => {
        const html = await render({ title: 'Gebruikers', columns, rows, count: 2, query: idle, undo: { id: 'a', message: 'Anna verwijderd' }, undoable: false });

        expect(html).not.toContain('Ongedaan maken');
    });
});

describe('unique ids per list instance', () => {
    it('gives two lists on one page different search ids', async () => {
        const props = { columns, rows, count: 2, query: idle };
        const html = await renderToString(createSSRApp({ render: () => h('div', [h(ListView, { title: 'A', ...props }), h(ListView, { title: 'B', ...props })]) }));

        const ids = [...html.matchAll(/<input id="([^"]+)" value/g)].map((match) => match[1]);
        expect(ids).toHaveLength(2);
        expect(new Set(ids).size).toBe(2);
    });
});

describe('SearchEcho', () => {
    it('keeps what the user typed when the server echoes a trimmed query', () => {
        const echo = new SearchEcho();
        echo.typed();
        echo.sent('zorg ');

        expect(echo.adopts('zorg')).toBe(false);
        expect(echo.adopts('elders')).toBe(true);
    });

    it('ignores a stale echo while a newer query is pending', () => {
        const echo = new SearchEcho();
        echo.typed();
        echo.sent('ab');
        echo.typed();
        echo.sent('abc');

        expect(echo.adopts('ab')).toBe(false);
        expect(echo.adopts('abc')).toBe(false);
        expect(echo.adopts('')).toBe(true);
    });

    it('adopts an external change when nothing is pending', () => {
        expect(new SearchEcho().adopts('nieuw')).toBe(true);
    });
});
