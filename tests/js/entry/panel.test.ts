import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ElementPanel from '../../../resources/js/pages/Canvas/ElementPanel.vue';
import { elementPath } from '../../../resources/js/pages/Canvas/entry';
import { entryElement } from './support';

const rows = [entryElement('d', 'primary_activities', 'domain', 'Opvang'), entryElement('a', 'primary_activities', 'activity', 'Peuters', 'd')];

const panel = async (list: boolean): Promise<string> => {
    const context: { teleports?: Record<string, string> } = {};
    await renderToString(createSSRApp({ render: () => h(ElementPanel, { canvasId: 1, element: { ...rows[1], description: 'Tekst' }, elements: rows, areas: [{ key: 'primary_activities', kind: 'activities', label: 'Primaire activiteiten' }], warnings: [], list, icons: ['users'] }) }), context);

    return (context.teleports?.['#detail-body'] ?? '').replace(/<!--.*?-->/g, '');
};

describe('elementPath', () => {
    it('joins the Area, the ancestors and the Element', () => {
        expect(elementPath(rows[1], rows, 'Primaire activiteiten')).toBe('Primaire activiteiten > Opvang > Peuters');
        expect(elementPath(rows[0], rows, 'Primaire activiteiten')).toBe('Primaire activiteiten > Opvang');
    });
});

describe('ElementPanel', () => {
    it('holds labelled name and description fields and the save button', async () => {
        const html = await panel(false);

        expect(html).toMatch(/<label[^>]*for="element-name"[^>]*>Naam<\/label>/);
        expect(html).toMatch(/<label[^>]*for="element-description"[^>]*>Omschrijving<\/label>/);
        expect(html).toContain('>Opslaan</button>');
        expect(html).toContain('value="Peuters"');
        expect(html).not.toContain('element-icon');
    });

    it('offers the icon only in list Areas', async () => {
        expect(await panel(true)).toContain('element-icon');
    });
});
