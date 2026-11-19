import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { parentOptions, parentValue, type EntryAreaInfo } from '../../../resources/js/pages/Canvas/entry';
import ParentSelect from '../../../resources/js/pages/Canvas/ParentSelect.vue';
import { entryElement } from './support';

const areas: EntryAreaInfo[] = [
    { key: 'primary_activities', kind: 'activities', label: 'Primaire activiteiten' },
    { key: 'supporting_activities', kind: 'activities', label: 'Ondersteunende activiteiten' },
    { key: 'actors', kind: 'list', label: 'Actoren' },
    { key: 'direction', kind: 'text', label: 'Koers' },
];
const rows = [
    entryElement('d', 'primary_activities', 'domain', 'Opvang'),
    entryElement('c', 'primary_activities', 'cluster', 'Peuters', 'd'),
    entryElement('a', 'primary_activities', 'activity', 'Spelen', 'd'),
    entryElement('d2', 'supporting_activities', 'domain', 'Facilitair'),
    entryElement('u', 'supporting_activities', 'domain', 'Onbekende allocatie', null, 9, { unknownAllocation: true }),
    entryElement('gone', 'primary_activities', 'domain', 'Weg', null, 3, { archived: true }),
    entryElement('g', 'actors', 'domain', 'Klanten'),
];

describe('parentOptions', () => {
    it('offers every Domain and Cluster of the activity Areas by path to an Activity, never the Unknown allocation Domain', () => {
        expect(parentOptions(rows[2], rows, areas)).toEqual([
            { value: 'd', label: 'Primaire activiteiten > Opvang' },
            { value: 'c', label: 'Primaire activiteiten > Opvang > Peuters' },
            { value: 'd2', label: 'Ondersteunende activiteiten > Facilitair' },
        ]);
    });

    it('offers only Domains to a Cluster', () => {
        expect(parentOptions(rows[1], rows, areas).map(({ value }) => value)).toEqual(['d', 'd2']);
    });

    it('offers the Areas of the same kind to a Domain, and Items only the groups of list Areas', () => {
        expect(parentOptions(rows[0], rows, areas).map(({ value }) => value)).toEqual(['area:primary_activities', 'area:supporting_activities']);
        expect(parentOptions(entryElement('i', 'actors', 'activity', 'Anna', 'g'), rows, areas).map(({ value }) => value)).toEqual(['g']);
    });

    it('names the current parent', () => {
        expect(parentValue(rows[2])).toBe('d');
        expect(parentValue(rows[0])).toBe('area:primary_activities');
    });
});

describe('ParentSelect', () => {
    it('is a labelled select', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(ParentSelect, { modelValue: 'd', options: [{ value: 'd', label: 'Primaire activiteiten > Opvang' }] }) }));

        expect(html).toMatch(/<label[^>]*for="element-parent"[^>]*>Hangt onder<\/label>/);
    });
});
