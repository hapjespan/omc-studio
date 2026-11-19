import type { SceneGraph } from '@open-pencil/scene-graph';
import { hexToColor } from './scene';

interface HistoryHost {
    commitMove(originals: Map<string, { x: number; y: number }>): void;
    commitResize(id: string, original: { x: number; y: number; width: number; height: number }): void;
    updateNodeWithUndo(id: string, changes: Record<string, unknown>, label?: string): void;
    alignNodes(ids: string[], axis: 'horizontal' | 'vertical', align: 'min' | 'center' | 'max'): void;
    distributeNodes(ids: string[], axis: 'horizontal' | 'vertical'): void;
}

export function dragBlocks(editor: HistoryHost, graph: SceneGraph, ids: string[], dx: number, dy: number) {
    const originals = new Map(ids.map((id) => [id, { x: graph.getNode(id)!.x, y: graph.getNode(id)!.y }]));
    for (const [id, origin] of originals) graph.updateNodePreview(id, { x: origin.x + dx, y: origin.y + dy });
    editor.commitMove(originals);
}

export function resizeBlock(editor: HistoryHost, graph: SceneGraph, id: string, width: number, height: number) {
    const node = graph.getNode(id)!;
    const original = { x: node.x, y: node.y, width: node.width, height: node.height };
    graph.updateNodePreview(id, { width, height });
    editor.commitResize(id, original);
}

export function restyleBlock(editor: HistoryHost, id: string, hex: string) {
    editor.updateNodeWithUndo(id, { fills: [{ type: 'SOLID', color: hexToColor(hex), opacity: 1, visible: true }] }, 'Style');
}

export function alignBlocks(editor: HistoryHost, ids: string[], axis: 'horizontal' | 'vertical', align: 'min' | 'center' | 'max') {
    editor.alignNodes(ids, axis, align);
}

export function distributeBlocks(editor: HistoryHost, ids: string[], axis: 'horizontal' | 'vertical') {
    editor.distributeNodes(ids, axis);
}
