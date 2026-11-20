import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';
import type { ElementLayout, LayoutElement } from '../../../resources/js/layout/types';

const manual = (x: number, y: number, width: number, height: number, z = 2): ElementLayout => ({ mode: 'manual', x, y, width, height, z });
const run = (elements: LayoutElement[], layouts: Record<string, ElementLayout>) => computeCanvasLayout({ ...withElements(elements), layouts }, fakeMeasurer);
const find = (canvas: ReturnType<typeof run>, id: string) => canvas.blocks.find((block) => block.id === id)!;
const domain = (id: string, name: string, position = 1) => element(id, 'primary_activities', 'domain', name, null, position);

describe('Growth of Manual layout', () => {
    it('breaks a word longer than the width over lines', () => {
        const canvas = run([domain('d', 'Onderwijsontwikkelingsprogramma')], { d: manual(10, 40, 100, 40) });
        expect(find(canvas, 'd').textLines.length).toBeGreaterThan(1);
        expect(find(canvas, 'd').textLines.join('')).toBe('Onderwijsontwikkelingsprogramma');
    });

    it('treats the stored height as a minimum that names make larger', () => {
        const canvas = run([domain('d', 'Een naam die over meerdere regels loopt in een smalle kaart')], { d: manual(10, 40, 100, 20) });
        const block = find(canvas, 'd');
        expect(block.drawnHeight).toBeGreaterThan(20);
        expect(block.drawnHeight).toBe(12 + block.textLines.length * 20 + 12);
    });

    it('keeps a stored height that is already larger than the content', () => {
        expect(find(run([domain('d', 'Kort')], { d: manual(10, 40, 200, 300) }), 'd').drawnHeight).toBe(300);
    });

    it('grows the parents with a child that hangs below them', () => {
        const elements = [domain('d', 'Dom'), element('k', 'primary_activities', 'cluster', 'Kluster', 'd'), element('a', 'primary_activities', 'activity', 'Les', 'k')];
        const canvas = run(elements, { d: manual(10, 40, 300, 60), k: manual(8, 30, 250, 40), a: manual(8, 100, 100, 30) });
        const [d, k, a] = ['d', 'k', 'a'].map((id) => find(canvas, id));
        expect(a.y + a.drawnHeight).toBe(k.y + 130);
        expect(k.drawnHeight).toBeGreaterThanOrEqual(130 + 8);
        expect(d.y + d.drawnHeight).toBeGreaterThanOrEqual(k.y + k.drawnHeight + 12);
    });

    it('never moves a Manual sibling when another grows', () => {
        const elements = [domain('d', 'Een naam die over meerdere regels loopt in een smalle kaart'), domain('e', 'Zorg', 2)];
        const small = run(elements, { d: manual(10, 40, 100, 20), e: manual(10, 80, 100, 20) });
        const large = run(elements, { d: manual(10, 40, 100, 200), e: manual(10, 80, 100, 20) });
        expect(find(small, 'e')).toEqual(find(large, 'e'));
        expect(find(small, 'd').drawnHeight).toBeGreaterThan(20);
    });

    it('re-flows automatic siblings below a sibling that grows', () => {
        const elements = [domain('d', 'Dom'), element('x', 'primary_activities', 'activity', 'Eerst', 'd', 1), element('y', 'primary_activities', 'activity', 'Tweede', 'd', 2), element('m', 'primary_activities', 'activity', 'Hand', 'x')];
        const plain = run(elements.slice(0, 3), {});
        const grown = run(elements, { m: manual(0, 60, 80, 30) });
        expect(find(grown, 'x').drawnHeight).toBeGreaterThan(find(plain, 'x').drawnHeight);
        expect(find(grown, 'y').y).toBeGreaterThan(find(plain, 'y').y);
    });

    it('grows a Manual Area to hold its content', () => {
        const elements = [domain('d', 'Dom'), domain('e', 'Zorg', 2)];
        const canvas = run(elements, { primary_activities: manual(560, 400, 1100, 20, 0), d: manual(10, 40, 300, 200) });
        expect(find(canvas, 'primary_activities').drawnHeight).toBeGreaterThanOrEqual(40 + 200 + 12);
    });

    it('grows a list Group with its Manual Item', () => {
        const list = [element('g', 'actors', 'domain', 'Ouders'), element('i', 'actors', 'activity', 'Moeder', 'g')];
        const canvas = run(list, { g: manual(10, 50, 200, 20), i: manual(0, 60, 100, 18) });
        expect(find(canvas, 'g').drawnHeight).toBeGreaterThanOrEqual(78);
    });
});
