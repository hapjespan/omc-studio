import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import type { CanvasLayoutInput, LayoutElement } from '../../../resources/js/layout';
import { buildMoveModel, judge, landing, type MoveBlock, type MoveModel } from '../../../resources/js/edit-engine/shiftMove';
import { MovePlacement } from '../../../resources/js/edit-engine/shiftGesture';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element, eightAreas, withElements } from '../layout/support';
import { manual, open } from './support';

const elements: LayoutElement[] = [
    element('dom-1', 'primary_activities', 'domain', 'First domain'),
    element('clu-1', 'primary_activities', 'cluster', 'First cluster', 'dom-1', 1),
    element('clu-3', 'primary_activities', 'cluster', 'Nested cluster', 'clu-1', 1),
    element('act-1', 'primary_activities', 'activity', 'Shared name', 'clu-1', 2),
    element('act-3', 'primary_activities', 'activity', 'Loose activity', 'dom-1', 2),
    element('act-6', 'primary_activities', 'activity', 'Wide activity', 'dom-1', 3),
    element('dom-2', 'primary_activities', 'domain', 'Second domain', null, 2),
    element('clu-2', 'primary_activities', 'cluster', 'Second cluster', 'dom-2', 1),
    element('act-5', 'primary_activities', 'activity', 'shared name', 'dom-2', 2),
    element('dom-3', 'supporting_activities', 'domain', 'Third domain'),
    element('dom-4', 'supporting_activities', 'domain', 'System domain', null, 2, { unknownAllocation: true }),
    element('grp-1', 'actors', 'domain', 'First group'),
    element('item-1', 'actors', 'activity', 'First item', 'grp-1', 1),
    element('grp-2', 'actors', 'domain', 'Second group', null, 2),
];

const layouts: CanvasLayoutInput['layouts'] = {
    primary_activities: manual(0, 200, 900, 600, 0),
    supporting_activities: manual(0, 820, 900, 360, 0),
    actors: manual(950, 200, 620, 400, 0),
    'dom-1': manual(0, 0, 400, 400),
    'clu-1': manual(20, 100, 300, 200),
    'clu-3': manual(10, 100, 200, 60),
    'act-1': manual(10, 20, 150, 40),
    'act-3': manual(20, 20, 150, 40),
    'act-6': manual(0, 330, 380, 40),
    'dom-2': manual(450, 0, 300, 400),
    'clu-2': manual(10, 100, 200, 100),
    'act-5': manual(10, 240, 150, 40),
    'dom-3': manual(0, 0, 300, 200),
    'dom-4': manual(400, 0, 200, 200),
    'grp-1': manual(0, 0, 300, 200),
    'item-1': manual(10, 40, 150, 30),
    'grp-2': manual(350, 0, 200, 200),
};

const structure = { areas: eightAreas, elements };
const canvas = computeCanvasLayout({ ...withElements(elements), layouts }, fakeMeasurer);
const model: MoveModel = buildMoveModel(canvas, structure);
const block = (id: string): MoveBlock => model.find((candidate) => candidate.id === id) as MoveBlock;
const at = (id: string, dx: number, dy: number) => ({ x: block(id).x + dx, y: block(id).y + dy });

describe('the model of the Shift move', () => {
    it('holds every Element block with its name, kind of Area and Unknown allocation flag', () => {
        expect(model.some((candidate) => candidate.id === 'primary_activities')).toBe(false);
        expect(block('act-3')).toMatchObject({ kind: 'activity', areaKey: 'primary_activities', areaKind: 'activities', parentId: 'dom-1', name: 'Loose activity', unknownAllocation: false });
        expect(block('item-1')).toMatchObject({ kind: 'activity', areaKind: 'list', parentId: 'grp-1' });
        expect(block('dom-4').unknownAllocation).toBe(true);
    });

    it('takes the target of an Activity over another Domain', () => {
        expect(judge(model, 'act-3', at('dom-2', 20, 50))).toEqual({ valid: true, targetId: 'dom-2', targetName: 'Second domain', narrowed: false });
    });

    it('takes the innermost target under the pointer', () => {
        expect(judge(model, 'act-3', at('clu-2', 20, 20))).toEqual({ valid: true, targetId: 'clu-2', targetName: 'Second cluster', narrowed: false });
    });

    it('counts a Cluster under the pointer as its Domain for a dragged Cluster', () => {
        expect(judge(model, 'clu-2', at('clu-1', 250, 20))).toEqual({ valid: true, targetId: 'dom-1', targetName: 'First domain', narrowed: false });
    });

    it('moves an Item to another Group of a list Area', () => {
        expect(judge(model, 'item-1', at('grp-2', 20, 20))).toEqual({ valid: true, targetId: 'grp-2', targetName: 'Second group', narrowed: false });
    });

    it('is a plain drag for a Domain, an Area and an unknown block', () => {
        expect(judge(model, 'dom-1', at('dom-2', 20, 50))).toBeNull();
        expect(judge(model, 'primary_activities', at('dom-2', 20, 50))).toBeNull();
        expect(judge(model, 'nothing', at('dom-2', 20, 50))).toBeNull();
    });

    it('is a plain drag over the current parent, over the block itself, over the Direction band and over nothing', () => {
        expect(judge(model, 'act-3', at('dom-1', 350, 20))).toBeNull();
        expect(judge(model, 'act-3', at('act-3', 5, 5))).toBeNull();
        expect(judge(model, 'act-3', { x: 400, y: 90 })).toBeNull();
        expect(judge(model, 'act-3', { x: 1670, y: 1180 })).toBeNull();
    });

    it('refuses an Area of the other kind, both ways', () => {
        expect(judge(model, 'act-3', at('grp-1', 200, 150))).toEqual({ valid: false, targetId: 'grp-1', targetName: 'First group', reason: 'other_kind' });
        expect(judge(model, 'item-1', at('dom-2', 20, 50))).toMatchObject({ valid: false, reason: 'other_kind' });
    });

    it('refuses the Unknown allocation Domain', () => {
        expect(judge(model, 'act-3', at('dom-4', 20, 20))).toEqual({ valid: false, targetId: 'dom-4', targetName: 'System domain', reason: 'unknown_allocation' });
    });

    it('refuses a name already used under the target, whatever its case', () => {
        expect(judge(model, 'act-1', at('dom-2', 20, 50))).toEqual({ valid: false, targetId: 'dom-2', targetName: 'Second domain', reason: 'name_taken' });
    });

    it('refuses a Cluster that would not hang under a Domain', () => {
        expect(judge(model, 'clu-2', at('clu-3', 20, 20))).toEqual({ valid: false, targetId: 'clu-1', targetName: 'First cluster', reason: 'cluster_needs_domain' });
    });

    it('refuses a Cluster in a list Area', () => {
        expect(judge(model, 'clu-2', at('grp-1', 200, 150))).toEqual({ valid: false, targetId: 'grp-1', targetName: 'First group', reason: 'no_clusters_in_list' });
    });

    it('tells that a block wider than its target gets narrower', () => {
        expect(judge(model, 'act-6', at('dom-3', 20, 150))).toEqual({ valid: true, targetId: 'dom-3', targetName: 'Third domain', narrowed: true });
    });
});

describe('the landing spot of the Shift move', () => {
    const target = () => block('dom-2');

    it('keeps the block where it was released, relative to the target', () => {
        expect(landing(model, 'act-3', 'dom-2', { x: target().x + 40, y: target().y + 60 })).toEqual({ x: 40, y: 60, width: 150, height: block('act-3').height, z: block('act-3').z });
    });

    it('pushes the block inside the target from the left, the top and the right', () => {
        expect(landing(model, 'act-3', 'dom-2', { x: target().x - 50, y: target().y - 30 })).toMatchObject({ x: 0, y: 0 });
        expect(landing(model, 'act-3', 'dom-2', { x: target().x + 290, y: target().y + 10 })).toMatchObject({ x: 150, width: 150 });
    });

    it('lets the block land below the target, which grows for it', () => {
        expect(landing(model, 'act-3', 'dom-2', { x: target().x, y: target().y + 500 })).toMatchObject({ y: 500 });
    });

    it('makes a block wider than its target exactly as wide as the target, keeping its height', () => {
        expect(landing(model, 'act-6', 'dom-3', { x: block('dom-3').x + 60, y: block('dom-3').y + 20 })).toEqual({ x: 0, y: 20, width: 300, height: block('act-6').height, z: block('act-6').z });
    });
});

describe('placing and reverting a Shift move in the scene', () => {
    const loaded = () => {
        const { graph, restrictions } = open(elements, layouts);
        return { graph, placement: new MovePlacement(graph, restrictions, model) };
    };

    it('places the block in the target at its landing spot and returns the intent', () => {
        const { graph, placement } = loaded();
        const intent = placement.previewMove('act-3', 'dom-2', { x: block('dom-2').x + 40, y: block('dom-2').y + 60 });
        expect(intent).toEqual({ kind: 'move', elementId: 'act-3', targetId: 'dom-2', layout: { x: 40, y: 60, width: 150, height: block('act-3').height, z: block('act-3').z }, narrowed: false });
        expect(graph.getNode('act-3')!.parentId).toBe('dom-2');
        expect([graph.getNode('act-3')!.x, graph.getNode('act-3')!.y, graph.getNode('act-3')!.width]).toEqual([40, 60, 150]);
    });

    it('puts the block back where it hung, in its old order and size', () => {
        const { graph, placement } = loaded();
        const before = { ...graph.getNode('act-3')!, index: graph.getNode('dom-1')!.childIds.indexOf('act-3') };
        placement.previewMove('act-6', 'dom-3');
        placement.previewMove('act-3', 'dom-2', { x: block('dom-2').x - 80, y: block('dom-2').y + 10 });
        placement.revert('act-3');
        const after = graph.getNode('act-3')!;
        expect([after.parentId, after.x, after.y, after.width, after.height]).toEqual([before.parentId, before.x, before.y, before.width, before.height]);
        expect(graph.getNode('dom-1')!.childIds.indexOf('act-3')).toBe(before.index);
    });

    it('places a block without a released point under the lowest child of the target, at its left edge', () => {
        const { graph, placement } = loaded();
        const intent = placement.previewMove('act-3', 'dom-2');
        const clusterBottom = graph.getNode('clu-2')!.y + graph.getNode('clu-2')!.height;
        expect(intent).toMatchObject({ layout: { x: 0, y: Math.max(clusterBottom, graph.getNode('act-5')!.y + graph.getNode('act-5')!.height) } });
    });

    it('narrows a block wider than its target when it is placed', () => {
        const { graph, placement } = loaded();
        placement.previewMove('act-6', 'dom-3', { x: block('dom-3').x + 60, y: block('dom-3').y + 20 });
        expect([graph.getNode('act-6')!.parentId, graph.getNode('act-6')!.x, graph.getNode('act-6')!.width]).toEqual(['dom-3', 0, 300]);
    });
});
