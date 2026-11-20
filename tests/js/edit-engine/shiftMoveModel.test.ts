import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import type { CanvasLayoutInput, ElementLayout, LayoutElement } from '../../../resources/js/layout';
import { fitChildren, judge, landing, buildMoveModel } from '../../../resources/js/edit-engine/shiftMove';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { eightAreas, element, withElements } from '../layout/support';
import { manual, open } from './support';
import { MovePlacement } from '../../../resources/js/edit-engine/shiftGesture';

const nl = JSON.parse(readFileSync('lang/nl.json', 'utf8')) as Record<string, string>;

const elements: LayoutElement[] = [
    element('d1', 'primary_activities', 'domain', 'Inkoop'),
    element('c1', 'primary_activities', 'cluster', 'Werving', 'd1', 1),
    element('a1', 'primary_activities', 'activity', 'Offreren', 'c1', 1),
    element('d2', 'primary_activities', 'domain', 'Verkoop', null, 2),
    element('a2', 'primary_activities', 'activity', 'Offreren', 'd2', 1),
    element('d3', 'supporting_activities', 'domain', 'Beheer', null, 1),
    element('u', 'supporting_activities', 'domain', 'Onbekende allocatie', null, 2, { unknownAllocation: true }),
    element('g1', 'actors', 'domain', 'Klanten', null, 1),
    element('i1', 'actors', 'activity', 'Jan', 'g1', 1),
    element('g2', 'actors', 'domain', 'Partners', null, 2),
];
const layouts: Record<string, ElementLayout> = {
    primary_activities: manual(780, 400, 880, 400, 0),
    d1: manual(20, 40, 300, 200),
    c1: manual(8, 40, 260, 120),
    a1: manual(8, 40, 200, 30),
    d2: manual(400, 40, 120, 100),
    d3: manual(20, 40, 300, 200),
    supporting_activities: manual(780, 820, 880, 340),
    u: manual(400, 40, 200, 100),
    actors: manual(12, 400, 740, 400, 0),
    g1: manual(20, 40, 200, 150),
    g2: manual(300, 40, 200, 150),
};
const input: CanvasLayoutInput = { ...withElements(elements), layouts };
const model = buildMoveModel(computeCanvasLayout(input, fakeMeasurer), { areas: eightAreas, elements });
const block = (id: string) => model.find((entry) => entry.id === id)!;
const inside = (id: string, dx = 5, dy = 5) => ({ x: block(id).x + dx, y: block(id).y + dy });

describe('Shift move model over the real structure', () => {
    it('takes the area kind and the Unknown allocation from the real Elements', () => {
        expect(block('a1').areaKind).toBe('activities');
        expect(block('i1').areaKind).toBe('list');
        expect(block('u').unknownAllocation).toBe(true);
        expect(block('d3').unknownAllocation).toBe(false);
    });

    it('moves an Item to another Group, and never an Item to an activity Area or an Activity to a Group', () => {
        expect(judge(model, 'i1', inside('g2'))).toMatchObject({ valid: true, targetId: 'g2' });
        expect(judge(model, 'i1', inside('d3'))).toMatchObject({ valid: false, reason: 'other_kind' });
        expect(judge(model, 'a1', inside('g2'))).toMatchObject({ valid: false, reason: 'other_kind' });
    });

    it('refuses the Unknown allocation Domain and a taken name with the reasons that carry the exact Invoeren messages', () => {
        const unknown = judge(model, 'a1', inside('u'));
        const taken = judge(model, 'a1', inside('d2'));
        expect(unknown).toMatchObject({ valid: false, reason: 'unknown_allocation' });
        expect(taken).toMatchObject({ valid: false, reason: 'name_taken' });
        expect(nl['drawing.refusal_unknown_allocation']).toBe(nl['rule.unknown_allocation_managed']);
        expect(nl['drawing.refusal_name_taken']).toBe(nl['rule.element_name_taken']);
        expect(nl['drawing.refusal_other_kind']).toBe(nl['rule.move_other_kind']);
    });

    it('lets a released block land below the target content: the target grows, so y is not clamped to its height', () => {
        const below = landing(model, 'a1', 'd3', { x: block('d3').x + 10, y: block('d3').y + 900 });
        expect(below.y).toBe(900);
        expect(below.x).toBe(10);
    });

    it('pushes a block released outside the target back inside, keeping its size', () => {
        const pushed = landing(model, 'a1', 'd3', { x: block('d3').x - 50, y: block('d3').y - 30 });
        expect([pushed.x, pushed.y, pushed.width, pushed.height]).toEqual([0, 0, 200, 30]);
        const right = landing(model, 'a1', 'd3', { x: block('d3').x + 290, y: block('d3').y + 5 });
        expect(right.x + right.width).toBeLessThanOrEqual(300);
    });

    it('narrows a wider block to the target width and pushes then narrows its Manual children', () => {
        const target = block('d2');
        const narrowed = landing(model, 'c1', 'd2', { x: target.x, y: target.y });
        expect(narrowed.width).toBe(target.width);
        expect(fitChildren([{ id: 'x', x: 60, width: 100 }], 100)).toEqual([{ id: 'x', x: 0, width: 100 }]);
        expect(fitChildren([{ id: 'x', x: 10, width: 50 }], 100)).toEqual([{ id: 'x', x: 10, width: 50 }]);
        expect(fitChildren([{ id: 'x', x: 0, width: 300 }], 100)).toEqual([{ id: 'x', x: 0, width: 100 }]);
        expect(fitChildren([{ id: 'x', x: 0, width: 300 }], 20)).toEqual([{ id: 'x', x: 0, width: 40 }]);
    });

    it('does the child push and narrowing in the scene when the block is placed, and revert puts everything back', () => {
        const { graph, restrictions } = open(elements, layouts);
        const placement = new MovePlacement(graph, restrictions, model);
        const clusterBefore = [graph.getNode('c1')!.width, graph.getNode('a1')!.x, graph.getNode('a1')!.width];
        placement.previewMove('c1', 'd2');
        expect(graph.getNode('c1')!.width).toBe(120);
        expect(graph.getNode('a1')!.x + graph.getNode('a1')!.width).toBeLessThanOrEqual(120);
        placement.revert('c1');
        expect([graph.getNode('c1')!.width, graph.getNode('a1')!.x, graph.getNode('a1')!.width]).toEqual(clusterBefore);
        expect(graph.getNode('c1')!.parentId).toBe('d1');
    });

    it('keeps a Cluster from landing in a list Area and an Item from a Cluster', () => {
        expect(judge(model, 'c1', inside('g2'))).toMatchObject({ valid: false });
        expect(judge(model, 'i1', inside('d1'))).toMatchObject({ valid: false, reason: 'other_kind' });
    });
});
