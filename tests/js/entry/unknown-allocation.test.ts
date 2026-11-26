import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ElementPanel from '../../../resources/js/pages/Canvas/ElementPanel.vue';
import EntryTree from '../../../resources/js/pages/Canvas/EntryTree.vue';
import { buildRows } from '../../../resources/js/pages/Canvas/entry';
import { entryElement } from './support';

const rows = [
    entryElement('u', 'supporting_activities', 'domain', 'Onbekende allocatie', null, 9, { unknownAllocation: true }),
    entryElement('d', 'supporting_activities', 'domain', 'Facilitair'),
];

describe('the Unknown allocation Domain in the tree', () => {
    it('is last, without arrows and without add line, marked Systeem', async () => {
        const built = buildRows(rows, 'supporting_activities');
        expect(built.map((row) => row.element.name)).toEqual(['Facilitair', 'Onbekende allocatie']);
        expect(built[1].closes).toBeNull();
        expect(built[0].last).toBe(true);

        const html = await renderToString(createSSRApp({ render: () => h(EntryTree, { rows: built, letters: {}, selectedId: null, label: 'x', canvasId: 1, itemLabel: 'Activiteit', clusters: true }) }));
        expect(html).toContain('Systeem');
        expect(html.match(/Onbekende allocatie/g)).toHaveLength(1);
        expect(html).not.toContain('Onbekende allocatie omhoog');
        expect(html.match(/toevoegen aan/g)).toHaveLength(1);
    });

    it('explains itself in the panel instead of offering fields', async () => {
        const context: { teleports?: Record<string, string> } = {};
        await renderToString(createSSRApp({ render: () => h(ElementPanel, { canvasId: 1, element: rows[0], elements: rows, areas: [], warnings: [], list: false, icons: [] }) }), context);
        const html = context.teleports?.['#detail-body'] ?? '';

        expect(html).toContain('Hier staan kostenposten die in dit canvas nog niet zijn ingedeeld');
        expect(html).not.toContain('Opslaan');
        expect(html).not.toContain('Archiveren');
    });
});
