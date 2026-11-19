import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { filterByName } from '../../../resources/js/pages/Canvas/entry';
import EntrySearch from '../../../resources/js/pages/Canvas/EntrySearch.vue';
import { entryElement } from './support';

const rows = [
    entryElement('d', 'primary_activities', 'domain', 'Opvang'),
    entryElement('c', 'primary_activities', 'cluster', 'Peuters', 'd'),
    entryElement('a', 'primary_activities', 'activity', 'Spelen', 'c'),
    entryElement('b', 'primary_activities', 'activity', 'Slapen', 'd'),
    entryElement('e', 'primary_activities', 'domain', 'Inkoop', null, 2),
];

describe('filterByName', () => {
    it('keeps matching rows with their parents, ignoring case', () => {
        expect(filterByName(rows, 'SPEL').map(({ id }) => id)).toEqual(['d', 'c', 'a']);
        expect(filterByName(rows, 'ink').map(({ id }) => id)).toEqual(['e']);
    });

    it('keeps everything for an empty text and nothing for no match', () => {
        expect(filterByName(rows, '  ')).toHaveLength(5);
        expect(filterByName(rows, 'zzz')).toEqual([]);
    });
});

describe('EntrySearch', () => {
    it('has a visible label', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(EntrySearch, { modelValue: '' }) }));

        expect(html).toMatch(/<label[^>]*for="entry-search"[^>]*>Zoek in deze area<\/label>/);
    });
});
