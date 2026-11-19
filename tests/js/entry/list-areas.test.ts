import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ListAreaEntry from '../../../resources/js/pages/Canvas/ListAreaEntry.vue';
import { buildRows } from '../../../resources/js/pages/Canvas/entry';
import { entryElement } from './support';

const rows = [
    entryElement('g', 'actors', 'domain', 'Klanten'),
    entryElement('i', 'actors', 'activity', 'Burgers', 'g', 1, { icon: 'users', modality: 'adaptive' }),
];

describe('ListAreaEntry', () => {
    it('shows Groep and the Item label, the icon, no Modality letter and no Cluster choice', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(ListAreaEntry, { canvasId: 1, itemLabel: 'Actor', rows: buildRows(rows, 'actors'), selectedId: null, label: 'x' }) }));

        expect(html).toContain('Groep');
        expect(html).toContain('>Actor</span>');
        expect(html).toContain('Mensen');
        expect(html).not.toContain('Modaliteit');
        expect(html).toContain('Actor toevoegen aan Klanten, Enter om op te slaan');
        expect(html).not.toContain('type="checkbox"');
    });
});
