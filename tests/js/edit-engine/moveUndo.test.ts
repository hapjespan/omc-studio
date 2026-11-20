import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import type { CanvasLayoutInput, LayoutElement } from '../../../resources/js/layout';
import type { LayoutRect, MoveIntent } from '../../../resources/js/edit-engine/DrawingEngine';
import { MoveHistory } from '../../../resources/js/edit-engine/moveHistory';
import { MovePlacement } from '../../../resources/js/edit-engine/shiftGesture';
import { buildMoveModel } from '../../../resources/js/edit-engine/shiftMove';
import { setBox } from '../../../resources/js/edit-engine/transform';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { eightAreas, element, withElements } from '../layout/support';
import { manual, open } from './support';

const elements: LayoutElement[] = [
    element('dom-1', 'primary_activities', 'domain', 'First domain'),
    element('act-1', 'primary_activities', 'activity', 'First activity', 'dom-1', 1),
    element('act-2', 'primary_activities', 'activity', 'Second activity', 'dom-1', 2),
    element('dom-2', 'primary_activities', 'domain', 'Second domain', null, 2),
    element('act-3', 'primary_activities', 'activity', 'Third activity', 'dom-2', 1),
];

const layouts: CanvasLayoutInput['layouts'] = {
    primary_activities: manual(0, 200, 900, 600, 0),
    'dom-1': manual(0, 0, 400, 400),
    'act-1': manual(10, 20, 150, 40),
    'act-2': manual(20, 100, 150, 40),
    'dom-2': manual(450, 0, 300, 400),
    'act-3': manual(10, 40, 150, 40),
};

const canvas = computeCanvasLayout({ ...withElements(elements), layouts }, fakeMeasurer);
const model = buildMoveModel(canvas, { areas: eightAreas, elements });
const moved = model.find((block) => block.id === 'act-1')!;

function start(manualLayouts: Record<string, LayoutRect> = {}) {
    const { graph, editor, restrictions } = open(elements, layouts);
    const placement = new MovePlacement(graph, restrictions, model);
    const intents: MoveIntent[] = [];
    const seen: Record<string, { parentId: string | null; x: number; y: number }> = {};
    const history = new MoveHistory(
        editor,
        placement,
        (id) => {
            const node = graph.getNode(id);
            seen[id] = { parentId: node?.parentId ?? null, x: node?.x ?? 0, y: node?.y ?? 0 };
            return manualLayouts[id] ?? null;
        },
        (intent) => intents.push(intent),
    );
    const box = (id: string) => {
        const node = graph.getNode(id)!;
        const parent = node.parentId ? graph.getNode(node.parentId) : null;
        return { parentId: node.parentId, x: node.x, y: node.y, width: node.width, height: node.height, index: parent ? parent.childIds.indexOf(id) : -1 };
    };
    return { editor, placement, history, intents, seen, box, stacks: () => [editor.undo.canUndo, editor.undo.canRedo] };
}

function moveAndCommit(setup: ReturnType<typeof start>): LayoutRect {
    setup.history.previewMove('act-1', 'dom-2');
    const layout = setup.intents[0].layout!;
    setup.history.commitMove('act-1', 'dom-2', layout, 'new');
    return layout;
}

describe('the panel route of the move', () => {
    it('places the block at the left edge under the lowest child of the target and emits a panel intent', () => {
        const setup = start();
        const lowest = setup.box('act-3').y + setup.box('act-3').height;
        setup.history.previewMove('act-1', 'dom-2');
        expect(setup.intents).toEqual([{ kind: 'move', elementId: 'act-1', targetId: 'dom-2', layout: { x: 0, y: lowest, width: moved.width, height: moved.height, z: moved.z }, origin: 'panel' }]);
        expect(setup.box('act-1').parentId).toBe('dom-2');
    });

    it('puts the block back and leaves nothing on the undo stack when the move is reverted', () => {
        const setup = start();
        const before = setup.box('act-1');
        setup.history.previewMove('act-1', 'dom-2');
        setup.history.revertMove('act-1');
        expect(setup.box('act-1')).toEqual(before);
        expect(setup.stacks()).toEqual([false, false]);
    });
});

describe('the drag route of the move', () => {
    it('captures the former state at the moment the gesture emits its intent', () => {
        const setup = start();
        const before = setup.box('act-1');
        const placed = setup.placement.previewMove('act-1', 'dom-2')!;
        setup.history.dragged(placed);
        expect(setup.intents).toEqual([{ ...placed, origin: 'drag' }]);
        expect(setup.seen['act-1']).toEqual({ parentId: before.parentId, x: before.x, y: before.y });
        expect(setup.box('act-1').parentId).toBe('dom-2');
        setup.history.commitMove('act-1', 'dom-2', placed.layout, 'new');
        setup.editor.undoAction();
        expect(setup.intents[1]).toEqual({ kind: 'move', elementId: 'act-1', targetId: 'dom-1', layout: null, position: before.index, origin: 'undo' });
    });
});

describe('the undo entry of an accepted move', () => {
    it('records exactly one entry', () => {
        const setup = start();
        moveAndCommit(setup);
        expect(setup.stacks()).toEqual([true, false]);
        expect(setup.editor.undo.peekUndo(1)).toBeUndefined();
    });

    it('emits an undo intent with the former parent, position and Automatic layout, leaving the scene alone', () => {
        const setup = start();
        const before = setup.box('act-1');
        moveAndCommit(setup);
        const placed = setup.box('act-1');
        setup.editor.undoAction();
        expect(setup.intents[1]).toEqual({ kind: 'move', elementId: 'act-1', targetId: 'dom-1', layout: null, position: before.index, origin: 'undo' });
        expect(setup.box('act-1')).toEqual(placed);
        expect(setup.stacks()).toEqual([false, true]);
    });

    it('carries the former Manual layout when the block had one', () => {
        const rect = { x: 10, y: 20, width: 150, height: 40, z: 2 };
        const setup = start({ 'act-1': rect });
        moveAndCommit(setup);
        setup.editor.undoAction();
        expect(setup.intents[1]).toMatchObject({ layout: rect, origin: 'undo' });
    });

    it('applies the scene change of a confirmed undo without pushing a new entry', () => {
        const setup = start();
        const before = setup.box('act-1');
        moveAndCommit(setup);
        setup.editor.undoAction();
        setup.history.commitMove('act-1', 'dom-1', null, 'undo');
        expect(setup.box('act-1')).toEqual(before);
        expect(setup.stacks()).toEqual([false, true]);
    });

    it('emits the redo intent and applies the scene change of a confirmed redo', () => {
        const setup = start();
        const layout = moveAndCommit(setup);
        const placed = setup.box('act-1');
        setup.editor.undoAction();
        setup.history.commitMove('act-1', 'dom-1', null, 'undo');
        setup.editor.redoAction();
        expect(setup.intents[2]).toEqual({ kind: 'move', elementId: 'act-1', targetId: 'dom-2', layout, origin: 'redo' });
        expect(setup.box('act-1').parentId).toBe('dom-1');
        setup.history.commitMove('act-1', 'dom-2', layout, 'redo');
        expect(setup.box('act-1')).toEqual(placed);
        expect(setup.stacks()).toEqual([true, false]);
    });
});

describe('a cancelled or refused confirmation', () => {
    it('leaves the stacks exactly as they were when an undo is cancelled', () => {
        const setup = start();
        moveAndCommit(setup);
        const placed = setup.box('act-1');
        setup.editor.undoAction();
        setup.history.cancel('undo');
        expect(setup.stacks()).toEqual([true, false]);
        expect(setup.intents).toHaveLength(2);
        expect(setup.box('act-1')).toEqual(placed);
    });

    it('leaves the stacks exactly as they were when a redo is cancelled', () => {
        const setup = start();
        moveAndCommit(setup);
        setup.editor.undoAction();
        setup.history.commitMove('act-1', 'dom-1', null, 'undo');
        const back = setup.box('act-1');
        setup.editor.redoAction();
        setup.history.cancel('redo');
        expect(setup.stacks()).toEqual([false, true]);
        expect(setup.intents).toHaveLength(3);
        expect(setup.box('act-1')).toEqual(back);
    });

    it('empties both stacks when the history is cleared', () => {
        const setup = start();
        moveAndCommit(setup);
        setup.editor.undoAction();
        setup.history.commitMove('act-1', 'dom-1', null, 'undo');
        setup.history.clear();
        expect(setup.stacks()).toEqual([false, false]);
    });
});

describe('ordinary layout edits around a move', () => {
    it('keeps its own entries in order before and after the move', () => {
        const setup = start();
        setBox(setup.editor, 'act-2', { x: 30 });
        moveAndCommit(setup);
        setBox(setup.editor, 'act-2', { y: 150 });
        setup.editor.undoAction();
        expect(setup.box('act-2').y).toBe(100);
        expect(setup.intents).toHaveLength(1);
        setup.editor.undoAction();
        expect(setup.intents[1]).toMatchObject({ elementId: 'act-1', origin: 'undo' });
        setup.history.commitMove('act-1', 'dom-1', null, 'undo');
        setup.editor.undoAction();
        expect(setup.box('act-2').x).toBe(20);
        expect(setup.intents).toHaveLength(2);
    });
});
