import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import AddDomain from '../../../resources/js/pages/Canvas/AddDomain.vue';
import { createdElement } from '../../../resources/js/pages/Canvas/entry';
import { entryElement } from './support';

describe('AddDomain', () => {
    it('is a form with one visible label named after the kind of the Area', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(AddDomain, { canvasId: 1, area: 'primary_activities', kindLabel: 'Domein' }) }));

        expect(html).toContain('<form');
        expect(html).toMatch(/<label[^>]*for="add-domain"[^>]*>\+ Domein<\/label>/);
    });
});

describe('createdElement', () => {
    const rows = [entryElement('1', 'steering', 'domain', 'Inkoop'), entryElement('2', 'primary_activities', 'domain', 'Inkoop'), entryElement('3', 'primary_activities', 'activity', 'Inkoop', '2')];

    it('finds the new Element by Area, kind, name and parent', () => {
        expect(createdElement(rows, 'primary_activities', 'domain', 'Inkoop', null)?.id).toBe('2');
        expect(createdElement(rows, 'primary_activities', 'activity', 'Inkoop', '2')?.id).toBe('3');
        expect(createdElement(rows, 'primary_activities', 'cluster', 'Inkoop', null)).toBeUndefined();
    });
});
