import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';
import type { ElementLayout, LayoutElement } from '../../../resources/js/layout/types';

const manual = (x: number, y: number, width: number, height: number, z = 2): ElementLayout => ({ mode: 'manual', x, y, width, height, z });
const run = (elements: LayoutElement[], layouts: Record<string, ElementLayout>) => computeCanvasLayout({ ...withElements(elements), layouts }, fakeMeasurer);
const find = (canvas: ReturnType<typeof run>, id: string) => canvas.blocks.find((block) => block.id === id)!;
const domain = element('d', 'primary_activities', 'domain', 'Dom');
const activity = (id: string, position: number) => element(id, 'primary_activities', 'activity', `Les ${id}`, 'd', position);

describe('Placement in a parent with Manual children', () => {
    it('places an automatic child under the lowest Manual child at its automatic width', () => {
        const canvas = run([domain, activity('a', 1), activity('b', 2)], { d: manual(10, 40, 300, 100), a: manual(8, 30, 120, 50) });
        const [d, a, b] = ['d', 'a', 'b'].map((id) => find(canvas, id));
        expect(b.y).toBe(a.y + a.drawnHeight + 6);
        expect(b.x).toBe(d.x + 12);
        expect(b.width).toBe(300 - 24);
    });

    it('keeps the automatic children in their order under the lowest Manual child', () => {
        const canvas = run([domain, activity('a', 1), activity('b', 2), activity('c', 3)], { d: manual(10, 40, 300, 100), a: manual(8, 90, 120, 50) });
        const [a, b, c] = ['a', 'b', 'c'].map((id) => find(canvas, id));
        expect(b.y).toBe(a.y + a.drawnHeight + 6);
        expect(c.y).toBe(b.y + b.drawnHeight + 6);
    });

    it('lets the automatic children jump when another sibling becomes Manual', () => {
        const elements = [domain, activity('a', 1), activity('b', 2), activity('c', 3)];
        const before = find(run(elements, { d: manual(10, 40, 300, 100), a: manual(8, 30, 120, 40) }), 'c').y;
        const after = find(run(elements, { d: manual(10, 40, 300, 100), a: manual(8, 30, 120, 40), b: manual(8, 150, 120, 40) }), 'c').y;
        expect(after).toBeGreaterThan(before);
    });

    it('flows from the top in a parent without Manual children', () => {
        const canvas = run([domain, activity('a', 1), activity('b', 2)], { d: manual(10, 40, 300, 100) });
        const [d, a] = ['d', 'a'].map((id) => find(canvas, id));
        expect(a.y).toBe(d.y + 12 + 20 + 6);
    });

    it('grows the parent and reports "Opmaak aangepast" when the stored height is too small', () => {
        const canvas = run([domain, activity('a', 1), activity('b', 2)], { d: manual(10, 40, 300, 60), a: manual(8, 30, 120, 40) });
        expect(find(canvas, 'd').drawnHeight).toBeGreaterThan(60);
        expect(canvas.warnings).toContainEqual({ kind: 'adjusted', id: 'd', name: 'Dom', area: 'primary_activities' });
    });

    it('reports nothing when the parent keeps its stored height', () => {
        const canvas = run([domain, activity('a', 1), activity('b', 2)], { d: manual(10, 40, 300, 400), a: manual(8, 30, 120, 40) });
        expect(canvas.warnings.filter((warning) => warning.kind === 'adjusted')).toEqual([]);
    });

    it('writes nothing to the Layout', () => {
        const layouts = { d: manual(10, 40, 300, 60), a: manual(8, 30, 120, 40) };
        const copy = structuredClone(layouts);
        run([domain, activity('a', 1), activity('b', 2)], layouts);
        expect(layouts).toEqual(copy);
    });

    it('places an automatic item of a list Group under its lowest Manual item', () => {
        const elements = [element('g', 'actors', 'domain', 'Ouders'), element('a', 'actors', 'activity', 'Moeder', 'g', 1), element('b', 'actors', 'activity', 'Vader', 'g', 2)];
        const canvas = run(elements, { g: manual(10, 60, 200, 100), a: manual(8, 30, 120, 40) });
        const [a, b] = ['a', 'b'].map((id) => find(canvas, id));
        expect(b.y).toBe(a.y + a.drawnHeight + 4);
        expect(canvas.warnings.filter((warning) => warning.kind === 'overlap')).toEqual([]);
    });
});
