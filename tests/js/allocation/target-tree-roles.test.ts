import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import TargetNode from '../../../resources/js/pages/Finance/TargetNode.vue';
import TargetTree from '../../../resources/js/pages/Finance/TargetTree.vue';
import type { TargetRow } from '../../../resources/js/allocation/types';

const rows = [
    { id: 'd', name: 'Inkoop', kind: 'domain', area: 'primary_activities', totalCents: 0, children: [{ id: 'a', name: 'Betalen', kind: 'activity', area: 'primary_activities', totalCents: 0, children: [] }] },
] as unknown as TargetRow[];

const render = (component: object, props: Record<string, unknown>) => renderToString(createSSRApp({ render: () => h(component, props) }));

describe('target lists', () => {
    it('TargetNode renders native nested lists without tree roles', async () => {
        const html = await render(TargetNode, { rows, inSet: new Set<string>(), needle: '', disabled: false, hideTotals: true, collapsible: false });
        expect(html).not.toMatch(/role="(tree|treeitem)"/);
        expect(html).not.toMatch(/<ul[^>]*role="group"/);
        expect(html).toContain('<li');
    });

    it('TargetTree renders no tree role', async () => {
        const html = await render(TargetTree, { targets: rows, inSet: [], disabled: false, figuresAvailable: false, year: null, areaTotals: null });
        expect(html).not.toMatch(/role="(tree|treeitem)"/);
        expect(html).toContain('role="group"');
    });
});
