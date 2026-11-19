import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { createEditor } from '@open-pencil/core/editor';
import { SceneGraph, type SceneNode } from '@open-pencil/scene-graph';
import { computeBlocks, type Fixture } from '../../../proof/wp-e0/layout';
import { clampToParent, inertShortcuts, restrict } from '../../../proof/wp-e0/restrict';
import { buildScene } from '../../../proof/wp-e0/scene';

const fixture: Fixture = JSON.parse(readFileSync('proof/wp-e0/fixtures/scene.json', 'utf8'));
const fake = { width: (text: string, font: { size: number }) => text.length * font.size * 0.5 };

function load() {
    const graph = new SceneGraph();
    buildScene(graph, fixture, computeBlocks(fixture, fake));
    const editor = createEditor({ graph, skipInitialGraphSetup: true });
    const restrictions = restrict(editor as unknown as Parameters<typeof restrict>[0], graph, ['area-direction', 'area-logo']);
    return { graph, editor, restrictions };
}

const count = (graph: SceneGraph) => [...graph.getAllNodes()].length;
const parentOf = (graph: SceneGraph, id: string) => graph.getNode(id)!.parentId;

describe('P5 restrictions', () => {
    it('refuses to create a node by any route', () => {
        const { graph, editor, restrictions } = load();
        const before = count(graph);
        expect(() => editor.createShape('RECTANGLE', 10, 10, 50, 50)).toThrow();
        expect(() => graph.createNode('FRAME', 'artboard')).toThrow();
        expect(graph.cloneTree('act-1', 'dom-2')).toBeNull();
        editor.select(['act-1']);
        try {
            editor.duplicateSelected();
        } catch {
            expect(count(graph)).toBe(before);
        }
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
        expect(graph.getNode('act-1')).toBeDefined();
    });

    it('refuses to change the parent of a node', () => {
        const { graph, editor, restrictions } = load();
        graph.reparentNode('act-3', 'dom-2');
        editor.reparentNodes(['act-1'], 'dom-3');
        graph.insertChildAt('act-5', 'dom-1', 0);
        expect(parentOf(graph, 'act-3')).toBe('dom-1');
        expect(parentOf(graph, 'act-1')).toBe('clu-1');
        expect(parentOf(graph, 'act-5')).toBe('dom-2');
        expect(restrictions.refusals.filter((refusal) => refusal.action === 'reparent').length).toBeGreaterThanOrEqual(2);
    });

    it('keeps the Direction band and the logo locked', () => {
        const { graph } = load();
        const band = graph.getNode('area-direction')!;
        const before = [band.x, band.y, band.width, band.height];
        graph.updateNode('area-direction', { x: 50, width: 100 });
        graph.updateNodePreview('area-logo', { y: 400 });
        graph.updateNodePositionPreview('area-logo', 9, 9);
        expect([band.x, band.y, band.width, band.height]).toEqual(before);
        expect(graph.getNode('area-logo')!.y).toBe(20);
        expect(band.locked && graph.getNode('area-logo')!.locked).toBe(true);
    });

    it('keeps the text nodes locked so they never act alone', () => {
        const { graph } = load();
        graph.updateNode('act-1#text', { x: 300 });
        expect(graph.getNode('act-1#text')!.x).toBe(fixture.padding);
    });

    it('keeps children inside their parent', () => {
        const { graph } = load();
        graph.updateNode('act-3', { x: 900, y: -40 });
        const node = graph.getNode('act-3')!;
        const parent = graph.getNode('dom-1')!;
        expect([node.x, node.y]).toEqual([parent.width - node.width, 0]);
        graph.updateNode('act-3', { width: 5000 });
        expect(graph.getNode('act-3')!.width).toBe(parent.width);
        graph.updateNodePreview('act-6', { x: -50 });
        expect(graph.getNode('act-6')!.x).toBe(0);
    });

    it('clamps without touching what the change does not name', () => {
        expect(clampToParent({ x: 5, y: 5, width: 20, height: 20 }, { width: 100, height: 100 }, { x: 95 })).toEqual({ x: 80 });
        expect(clampToParent({ x: 5, y: 5, width: 20, height: 20 }, { width: 100, height: 100 }, { fills: [] } as Partial<SceneNode>)).toEqual({ fills: [] });
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

    it('lets a move inside the parent through', () => {
        const { graph } = load();
        graph.updateNode('act-3', { x: 30, y: 40 });
        expect([graph.getNode('act-3')!.x, graph.getNode('act-3')!.y]).toEqual([30, 40]);
    });
});
