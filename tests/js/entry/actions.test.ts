import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ElementActions from '../../../resources/js/pages/Canvas/ElementActions.vue';
import { descendantNames } from '../../../resources/js/pages/Canvas/entry';
import { entryElement } from './support';

const rows = [
    entryElement('d', 'primary_activities', 'domain', 'Opvang'),
    entryElement('c', 'primary_activities', 'cluster', 'Peuters', 'd'),
    entryElement('a', 'primary_activities', 'activity', 'Spelen', 'c'),
    entryElement('b', 'primary_activities', 'activity', 'Slapen', 'd'),
];

describe('descendantNames', () => {
    it('lists the whole subtree depth first', () => {
        expect(descendantNames(rows[0], rows)).toEqual(['Peuters', 'Spelen', 'Slapen']);
        expect(descendantNames(rows[2], rows)).toEqual([]);
    });
});

describe('ElementActions', () => {
    it('shows Archiveren and Verwijderen as buttons', async () => {
        const html = (await renderToString(createSSRApp({ render: () => h(ElementActions, { canvasId: 1, element: rows[1], elements: rows }) }))).replace(/<!--.*?-->/g, '');

        expect(html).toContain('>Archiveren</button>');
        expect(html).toContain('>Verwijderen</button>');
    });
});
