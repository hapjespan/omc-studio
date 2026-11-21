import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { entryWarnings, type EntryAreaInfo } from '../../../resources/js/pages/Canvas/entry';
import { fakeMeasurer } from './fakeMeasurer';
import { eightAreas, element, withElements } from './support';
import { entryElement } from '../entry/support';
import type { ElementLayout, LayoutElement } from '../../../resources/js/layout/types';

const manual = (x: number, y: number, width: number, height: number, z = 2): ElementLayout => ({ mode: 'manual', x, y, width, height, z });
const run = (elements: LayoutElement[], layouts: Record<string, ElementLayout>) => computeCanvasLayout({ ...withElements(elements), layouts }, fakeMeasurer);
const domain = (id: string, name: string, position = 1, area: 'primary_activities' | 'actors' = 'primary_activities') => element(id, area, 'domain', name, null, position);
const overlaps = (canvas: ReturnType<typeof run>) => canvas.warnings.filter((warning) => warning.kind === 'overlap');

describe('overlap warnings', () => {
    it('pairs two Manual Domains that overlap and names both', () => {
        const canvas = run([domain('a', 'Alfa'), domain('b', 'Beta', 2)], { a: manual(10, 40, 200, 100), b: manual(100, 80, 200, 100) });
        expect(overlaps(canvas)).toEqual([{ kind: 'overlap', id: 'a', name: 'Alfa', otherId: 'b', other: 'Beta', area: 'primary_activities' }]);
    });

    it('does not warn for blocks that only touch or are apart', () => {
        const canvas = run([domain('a', 'Alfa'), domain('b', 'Beta', 2)], { a: manual(10, 40, 100, 100), b: manual(110, 40, 100, 100) });
        expect(overlaps(canvas)).toEqual([]);
    });

    it('does not warn for a child inside its parent or for an automatic Canvas', () => {
        const elements = [domain('a', 'Alfa'), element('x', 'primary_activities', 'activity', 'Les', 'a')];
        expect(overlaps(run(elements, { a: manual(10, 40, 200, 100) }))).toEqual([]);
        expect(overlaps(run(elements, {}))).toEqual([]);
    });

    it('pairs Manual siblings inside a parent', () => {
        const elements = [domain('a', 'Alfa'), element('x', 'primary_activities', 'activity', 'Een', 'a', 1), element('y', 'primary_activities', 'activity', 'Twee', 'a', 2)];
        const canvas = run(elements, { a: manual(10, 40, 300, 200), x: manual(10, 40, 100, 50), y: manual(50, 60, 100, 50) });
        expect(overlaps(canvas)).toEqual([{ kind: 'overlap', id: 'x', name: 'Een', otherId: 'y', other: 'Twee', area: 'primary_activities' }]);
    });

    it('pairs two Areas that overlap, by their labels', () => {
        const canvas = run([], { primary_activities: manual(560, 400, 1100, 300, 0), supporting_activities: manual(600, 600, 1000, 300, 0) });
        expect(overlaps(canvas)).toContainEqual({ kind: 'overlap', id: 'primary_activities', name: 'Primaire activiteiten', otherId: 'supporting_activities', other: 'Ondersteunende activiteiten', area: 'primary_activities' });
    });

    it('grows past the Artboard without refusing and warns for the Domain', () => {
        const canvas = run([domain('a', 'Alfa')], { primary_activities: manual(560, 400, 1100, 300, 0), a: manual(10, 40, 300, 900) });
        expect(canvas.warnings).toContainEqual({ kind: 'beyondArtboard', id: 'a', name: 'Alfa', area: 'primary_activities' });
        expect(canvas.beyondArtboardCount).toBe(1);
    });
});

describe('overlap in the Invoeren list', () => {
    const areas = eightAreas as unknown as EntryAreaInfo[];
    const rows = [entryElement('a', 'primary_activities', 'domain', 'Alfa'), entryElement('b', 'primary_activities', 'domain', 'Beta', null, 2)];

    it('lists "Overlapt" with both names under the first Element', () => {
        const warnings = entryWarnings(rows, areas, 'primary_activities', fakeMeasurer, { a: manual(10, 40, 200, 100), b: manual(100, 80, 200, 100) });
        expect(warnings).toContainEqual({ id: 'a', key: 'layout.warning.overlap', name: 'Alfa', other: 'Beta' });
    });

    it('lists no Area pair, because an Area cannot be selected in Invoeren', () => {
        const warnings = entryWarnings([], areas, 'primary_activities', fakeMeasurer, { primary_activities: manual(560, 400, 1100, 300, 0), supporting_activities: manual(600, 600, 1000, 300, 0) });
        expect(warnings).toEqual([]);
    });
});

describe('Warnings list', () => {
    it('renders the overlap text with both names', async () => {
        const { createSSRApp, h } = await import('vue');
        const { renderToString } = await import('vue/server-renderer');
        const { default: Warnings } = await import('../../../resources/js/pages/Canvas/Warnings.vue');
        const html = await renderToString(createSSRApp({ render: () => h(Warnings, { warnings: [{ id: 'a', key: 'layout.warning.overlap', name: 'Alfa', other: 'Beta' }] }) }));
        expect(html).toMatch(/Overlapt:<\/span> (<!--\]-->)?(<!--\[-->)?Alfa en Beta/);
    });
});
