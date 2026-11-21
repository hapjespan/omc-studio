import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';
import type { ElementLayout, LayoutElement } from '../../../resources/js/layout/types';

const manual = (x: number, y: number, width: number, height: number, z = 2): ElementLayout => ({ mode: 'manual', x, y, width, height, z });
const run = (elements: LayoutElement[], layouts: Record<string, ElementLayout>) => computeCanvasLayout({ ...withElements(elements), layouts }, fakeMeasurer);
const find = (canvas: ReturnType<typeof run>, id: string) => canvas.blocks.find((block) => block.id === id)!;

const tree = [
    element('d', 'primary_activities', 'domain', 'Onderwijs', null, 1),
    element('a', 'primary_activities', 'activity', 'Les', 'd', 1),
    element('e', 'primary_activities', 'domain', 'Zorg', null, 2),
];

describe('Manual layout', () => {
    it('draws an Area at its stored place and size', () => {
        const area = find(run(tree, { primary_activities: manual(560, 400, 1100, 380, 0) }), 'primary_activities');
        expect([area.x, area.y, area.width, area.drawnHeight]).toEqual([560, 400, 1100, 380]);
    });

    it('draws a Domain at its stored place relative to its Area, in Artboard pixels', () => {
        const canvas = run(tree, { primary_activities: manual(560, 400, 1100, 380, 0), d: manual(16, 48, 286, 204) });
        const domain = find(canvas, 'd');
        expect([domain.x, domain.y, domain.width, domain.drawnHeight, domain.z]).toEqual([576, 448, 286, 204, 1002]);
    });

    it('places a Manual Domain relative to an automatic Area too', () => {
        const canvas = run(tree, { d: manual(30, 60, 300, 100) });
        const area = find(canvas, 'primary_activities');
        const domain = find(canvas, 'd');
        expect([domain.x, domain.y]).toEqual([area.x + 30, area.y + 60]);
    });

    it('flows the Activities of a Manual Domain inside its width', () => {
        const canvas = run(tree, { d: manual(30, 60, 300, 100) });
        const domain = find(canvas, 'd');
        const activity = find(canvas, 'a');
        expect(activity.x).toBe(domain.x + 12);
        expect(activity.width).toBe(300 - 24);
    });

    it('places a Manual child relative to its parent and keeps its z within its depth', () => {
        const canvas = run(tree, { d: manual(30, 60, 300, 200), a: manual(10, 40, 120, 30, 7) });
        const parent = find(canvas, 'd');
        const child = find(canvas, 'a');
        expect([child.x, child.y, child.width, child.drawnHeight, child.z]).toEqual([parent.x + 10, parent.y + 40, 120, 30, 3007]);
    });

    it('places the other Domains under the lowest Manual Domain of the Area', () => {
        const canvas = run(tree, { d: manual(30, 60, 300, 100) });
        const hand = find(canvas, 'd');
        const other = find(canvas, 'e');
        expect(other.y).toBeGreaterThanOrEqual(hand.y + hand.drawnHeight);
        expect(canvas.warnings.filter((warning) => warning.kind === 'overlap')).toEqual([]);
    });

    it('keeps the other Domains in order and automatic under the lowest of several Manual Domains', () => {
        const more = [...tree, element('f', 'primary_activities', 'domain', 'Zakelijk', null, 3), element('g', 'primary_activities', 'domain', 'Extra', null, 4)];
        const canvas = run(more, { d: manual(30, 60, 300, 100), f: manual(400, 200, 300, 150) });
        const lowest = Math.max(...['d', 'f'].map((id) => find(canvas, id).y + find(canvas, id).drawnHeight));
        expect(find(canvas, 'e').y).toBeGreaterThanOrEqual(lowest);
        expect(find(canvas, 'g').x).toBeGreaterThan(find(canvas, 'e').x);
    });

    it('keeps automatic Domains at the top of an Area without a Manual Domain', () => {
        const canvas = run(tree, {});
        expect(find(canvas, 'e').y).toBe(find(canvas, 'd').y);
    });

    it('ignores an incomplete or automatic entry', () => {
        const plain = run(tree, {});
        expect(run(tree, { d: { mode: 'automatic' }, e: { mode: 'manual', x: 1 } })).toEqual(plain);
    });

    it('places a Manual Group of a list Area', () => {
        const list = [element('g', 'actors', 'domain', 'Ouders'), element('i', 'actors', 'activity', 'Moeder', 'g')];
        const canvas = run(list, { g: manual(10, 50, 200, 80) });
        const area = find(canvas, 'actors');
        expect([find(canvas, 'g').x, find(canvas, 'g').y]).toEqual([area.x + 10, area.y + 50]);
        expect(find(canvas, 'i').x).toBeGreaterThan(find(canvas, 'g').x);
    });
});

describe('Stacking', () => {
    it('keeps a child above its parent and a parent above its Area whatever the sibling order values', () => {
        const canvas = run(tree, { primary_activities: manual(560, 400, 1100, 380, 3), d: manual(16, 48, 286, 204, 1), a: manual(8, 30, 100, 30, 0) });
        const [area, parent, child] = ['primary_activities', 'd', 'a'].map((id) => find(canvas, id).z);
        expect(parent).toBeGreaterThan(area);
        expect(child).toBeGreaterThan(parent);
    });

    it('orders siblings by their stored value', () => {
        const canvas = run(tree, { d: manual(16, 48, 100, 100, 0), e: manual(40, 60, 100, 100, 1) });
        expect(find(canvas, 'e').z).toBeGreaterThan(find(canvas, 'd').z);
    });
});
