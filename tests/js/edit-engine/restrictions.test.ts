import { createEditor } from '@open-pencil/core/editor';
import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { clampToParent, inertShortcuts, restrict } from '../../../resources/js/edit-engine/restrictions';
import { buildScene } from '../../../resources/js/edit-engine/scene';
import { keepSelectable } from '../../../resources/js/edit-engine/selection';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element, withElements } from '../layout/support';

const elements = [
    element('dom-1', 'primary_activities', 'domain', 'First domain'),
    element('clu-1', 'primary_activities', 'cluster', 'Cluster', 'dom-1', 1),
    element('act-1', 'primary_activities', 'activity', 'First activity', 'clu-1', 1),
    element('act-3', 'primary_activities', 'activity', 'Loose activity', 'dom-1', 2),
    element('dom-2', 'supporting_activities', 'domain', 'Second domain'),
    element('act-5', 'supporting_activities', 'activity', 'Other activity', 'dom-2', 1),
];

function load(onText: () => void = () => undefined) {
    const graph = buildScene(computeCanvasLayout(withElements(elements), fakeMeasurer));
    const editor = createEditor({ graph, skipInitialGraphSetup: true });
    const restrictions = restrict(editor, graph, ['direction'], onText);
    return { graph, editor, restrictions };
}

const count = (graph: ReturnType<typeof load>['graph']) => [...graph.getAllNodes()].length;

describe('restrictions of the drawing', () => {
    it('refuses to create a node by any route', () => {
        const { graph, editor, restrictions } = load();
        const before = count(graph);
        expect(() => editor.createShape('RECTANGLE', 10, 10, 50, 50)).toThrow();
        expect(() => graph.createNode('FRAME', 'artboard')).toThrow();
        expect(graph.cloneTree('act-1', 'dom-2')).toBeNull();
        expect(count(graph)).toBe(before);
        expect(restrictions.refusals.some((refusal) => refusal.action === 'create')).toBe(true);
    });

    it('refuses to delete a node', () => {
        const { graph, editor } = load();
        const before = count(graph);
        editor.select(['act-1', 'dom-2']);
        editor.deleteSelected();
        graph.deleteNode('act-3');
        expect(count(graph)).toBe(before);
    });

    it('refuses to change the parent of a node', () => {
        const { graph, editor, restrictions } = load();
        graph.reparentNode('act-3', 'dom-2');
        editor.reparentNodes(['act-1'], 'dom-2');
        expect(graph.getNode('act-3')!.parentId).toBe('dom-1');
        expect(graph.getNode('act-1')!.parentId).toBe('clu-1');
        expect(restrictions.refusals.filter((refusal) => refusal.action === 'reparent').length).toBeGreaterThanOrEqual(1);
    });

    it('keeps the Direction band and the text nodes locked', () => {
        const { graph } = load();
        const band = graph.getNode('direction')!;
        const before = [band.x, band.y, band.width, band.height];
        graph.updateNode('direction', { x: 50, width: 100 });
        graph.updateNodePreview('direction', { y: 400 });
        graph.updateNodePositionPreview('direction', 9, 9);
        expect([band.x, band.y, band.width, band.height]).toEqual(before);
        expect(band.locked).toBe(true);
        const text = graph.getNode('act-3#text')!;
        graph.updateNode('act-3#text', { x: 300 });
        expect(graph.getNode('act-3#text')!.x).toBe(text.x);
    });

    it('keeps children inside their parent and lets a move inside through', () => {
        const { graph } = load();
        const parent = graph.getNode('dom-1')!;
        graph.updateNode('act-3', { x: 900, y: -40 });
        const node = graph.getNode('act-3')!;
        expect([node.x, node.y]).toEqual([parent.width - node.width, 0]);
        graph.updateNode('act-3', { x: 10, y: 40 });
        expect([graph.getNode('act-3')!.x, graph.getNode('act-3')!.y]).toEqual([10, 40]);
    });

    it('clamps without touching what the change does not name', () => {
        expect(clampToParent({ x: 5, y: 5, width: 20, height: 20 }, { width: 100, height: 100 }, { x: 95 })).toEqual({ x: 80 });
    });

    it('accepts only the select and hand tools', () => {
        const { editor, restrictions } = load();
        editor.setTool('RECTANGLE');
        editor.setTool('TEXT');
        editor.setTool('PEN');
        expect(editor.state.activeTool).toBe('SELECT');
        editor.setTool('HAND');
        expect(editor.state.activeTool).toBe('HAND');
        expect(restrictions.refusals.filter((refusal) => refusal.action === 'tool').length).toBe(3);
    });

    it('records the library shortcuts that must stay inert', () => {
        expect(inertShortcuts).toEqual(expect.arrayContaining(['KeyF', 'KeyR', 'KeyO', 'KeyL', 'KeyT', 'KeyP', 'Shift+KeyP', 'KeyC', 'KeyA', 'Shift+KeyS', 'Delete', 'Backspace']));
        expect(inertShortcuts).not.toContain('KeyV');
        expect(inertShortcuts).not.toContain('KeyH');
    });

    it('refuses text editing and reports the attempt', () => {
        let hints = 0;
        const { editor, graph } = load(() => hints++);
        const before = graph.getNode('act-3#text')!.text;
        editor.startTextEditing('act-3#text');
        expect(hints).toBe(1);
        expect(editor.textEditor).toBeNull();
        expect(graph.getNode('act-3#text')!.text).toBe(before);
    });

    it('gives the library back when released', () => {
        const { graph, editor, restrictions } = load();
        restrictions.release();
        editor.setTool('RECTANGLE');
        expect(editor.state.activeTool).toBe('RECTANGLE');
        graph.deleteNode('act-3');
        expect(graph.getNode('act-3')).toBeUndefined();
    });
});

describe('selection of the drawing', () => {
    it('never selects the Direction band or a text node', () => {
        const { editor } = load();
        keepSelectable(editor, ['direction']);
        editor.select(['direction', 'act-3#text', 'act-3']);
        expect([...editor.state.selectedIds]).toEqual(['act-3']);
        editor.select(['direction']);
        expect([...editor.state.selectedIds]).toEqual([]);
    });
});

describe('selection events of the drawing', () => {
    it('reports the selection that remains after the filter', async () => {
        const { watchSelection } = await import('../../../resources/js/edit-engine/selection');
        const { editor } = load();
        keepSelectable(editor, ['direction']);
        const seen: string[][] = [];
        watchSelection(editor, (ids) => seen.push(ids));
        editor.select(['act-3']);
        editor.select(['act-3', 'act-1']);
        expect(seen.at(-1)).toEqual(expect.arrayContaining(['act-3', 'act-1']));
        expect([...editor.state.selectedIds].sort()).toEqual(['act-1', 'act-3']);
        editor.select(['direction', 'act-3']);
        expect([...editor.state.selectedIds]).toEqual(['act-3']);
    });
});
