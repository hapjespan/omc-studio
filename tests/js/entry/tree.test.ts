import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import EntryTree from '../../../resources/js/pages/Canvas/EntryTree.vue';
import { buildRows, kindLabelKey } from '../../../resources/js/pages/Canvas/entry';
import { entryElement } from './support';

const elements = [
    entryElement('d1', 'primary_activities', 'domain', 'Inkoop'),
    entryElement('c1', 'primary_activities', 'cluster', 'Contracten', 'd1', 1),
    entryElement('a1', 'primary_activities', 'activity', 'Werving', 'd1', 2),
    entryElement('a2', 'primary_activities', 'activity', 'Offerte', 'c1', 1),
    entryElement('a3', 'primary_activities', 'activity', 'Selectie', 'd1', 1, { modality: 'adaptive' }),
    entryElement('d2', 'primary_activities', 'domain', 'Verkoop', null, 2),
    entryElement('x', 'steering', 'domain', 'Elders'),
];

describe('buildRows', () => {
    it('lists Activities before Clusters with Cluster Activities one level deeper', () => {
        expect(buildRows(elements, 'primary_activities').map(({ element: { name }, depth }) => `${depth}:${name}`)).toEqual(['0:Inkoop', '1:Selectie', '1:Werving', '1:Contracten', '2:Offerte', '0:Verkoop']);
    });

    it('marks the first and last of a kind within one parent', () => {
        const rows = buildRows(elements, 'primary_activities');
        const flags = (name: string) => rows.filter((row) => row.element.name === name).map(({ first, last }) => [first, last])[0];

        expect(flags('Selectie')).toEqual([true, false]);
        expect(flags('Werving')).toEqual([false, true]);
        expect(flags('Contracten')).toEqual([true, true]);
        expect(flags('Inkoop')).toEqual([true, false]);
    });

    it('names the kind per Area', () => {
        expect(kindLabelKey('domain', 'actors')).toBe('areas.actors.level_1');
        expect(kindLabelKey('activity', 'actors')).toBe('areas.actors.level_2');
        expect(kindLabelKey('cluster', 'steering')).toBe('entry.kind.cluster');
    });
});

describe('EntryTree', () => {
    it('renders kind, name and Modality letter as one button per row', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(EntryTree, { rows: buildRows(elements, 'primary_activities'), letters: { adaptive: 'A' }, selectedId: 'a3', label: 'Structuur' }) }));

        expect(html).toContain('aria-label="Structuur"');
        expect(html.match(/<button/g)).toHaveLength(6);
        expect(html).toMatch(/Activiteit<\/span>.*Selectie.*Modaliteit<\/span>A/);
        expect(html.match(/aria-current="true"/g)).toHaveLength(1);
    });
});
