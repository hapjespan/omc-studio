import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { buildRows, entryWarnings, type EntryAreaInfo } from '../../../resources/js/pages/Canvas/entry';
import Warnings from '../../../resources/js/pages/Canvas/Warnings.vue';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { eightAreas } from '../layout/support';
import { entryElement } from './support';

const areas = eightAreas as unknown as EntryAreaInfo[];
const rows = [
    entryElement('d1', 'primary_activities', 'domain', 'Opvang'),
    entryElement('c1', 'primary_activities', 'cluster', 'Peuters', 'd1'),
    entryElement('d2', 'primary_activities', 'domain', 'Facilitair', null, 2),
    entryElement('g1', 'actors', 'domain', 'Klanten'),
];

describe('entryWarnings', () => {
    it('lists the warnings of the chosen Area with their message keys', () => {
        expect(entryWarnings(rows, areas, 'primary_activities', fakeMeasurer)).toEqual([
            { id: 'd1', key: 'entry.warning.domain_without_activities', name: 'Opvang' },
            { id: 'c1', key: 'entry.warning.empty_cluster', name: 'Peuters' },
            { id: 'd2', key: 'entry.warning.domain_without_activities', name: 'Facilitair' },
        ]);
    });

    it('says group without items in list Areas and ignores archived Elements', () => {
        expect(entryWarnings(rows, areas, 'actors', fakeMeasurer).map(({ key }) => key)).toEqual(['entry.warning.group_without_items']);
        expect(entryWarnings(rows.map((row) => ({ ...row, archived: true })), areas, 'actors', fakeMeasurer)).toEqual([]);
    });
});

describe('Warnings and the empty mark', () => {
    it('renders each warning as text with the count, and nothing when there are none', async () => {
        const render = (warnings: unknown[]) => renderToString(createSSRApp({ render: () => h(Warnings, { warnings }) }));

        const html = await render([{ id: 'c1', key: 'entry.warning.empty_cluster', name: 'Peuters' }]);
        expect(html).toContain('Waarschuwingen (1)');
        expect(html).toMatch(/Leeg cluster:<\/span> (<!--\[-->)?Peuters/);
        expect(await render([])).not.toContain('Waarschuwingen');
    });

    it('marks an empty Cluster in the rows', () => {
        expect(buildRows(rows, 'primary_activities').filter((row) => row.empty).map((row) => row.element.name)).toEqual(['Peuters']);
    });
});
