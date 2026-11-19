import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ArchivedToggle from '../../../resources/js/pages/Canvas/ArchivedToggle.vue';
import EntryTree from '../../../resources/js/pages/Canvas/EntryTree.vue';
import { buildRows } from '../../../resources/js/pages/Canvas/entry';
import { entryElement } from './support';

const rows = [
    entryElement('d', 'primary_activities', 'domain', 'Opvang', null, 1, { archived: true }),
    entryElement('a', 'primary_activities', 'activity', 'Spelen', 'd', 1, { archived: true }),
];

describe('archived rows', () => {
    it('are marked, offer Terugzetten instead of arrows and carry no add line', async () => {
        const built = buildRows(rows, 'primary_activities');
        const html = await renderToString(createSSRApp({ render: () => h(EntryTree, { rows: built, letters: {}, selectedId: null, label: 'x', canvasId: 1, itemLabel: 'Activiteit', clusters: true }) }));

        expect(html.match(/Gearchiveerd/g)).toHaveLength(2);
        expect(html).toContain('aria-label="Spelen terugzetten"');
        expect(html).not.toContain('omhoog');
        expect(html).not.toContain('toevoegen aan');
    });

    it('are switched on by a labelled checkbox', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(ArchivedToggle, { modelValue: false }) }));

        expect(html).toMatch(/<label[^>]*><input[^>]*type="checkbox"[^>]*>Toon gearchiveerd<\/label>/);
    });
});
