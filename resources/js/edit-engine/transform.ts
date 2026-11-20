import type { Editor } from '@open-pencil/core/editor';
import type { Box } from './constraints';

export interface Selection {
    editor: Editor;
    ids: string[];
}

export function setBox(editor: Editor, id: string, box: Partial<Box>): void {
    editor.updateNodeWithUndo(id, box, 'Plek en grootte');
}

export function nudge({ editor, ids }: Selection, dx: number, dy: number): void {
    editor.undo.runBatch('Verplaatsen', () => {
        for (const id of ids) {
            const node = editor.graph.getNode(id);
            if (node) editor.updateNodeWithUndo(id, { x: node.x + dx, y: node.y + dy }, 'Verplaatsen');
        }
    });
}

export function resizeBy({ editor, ids }: Selection, dw: number, dh: number): void {
    editor.undo.runBatch('Grootte', () => {
        for (const id of ids) {
            const node = editor.graph.getNode(id);
            if (node) editor.updateNodeWithUndo(id, { width: node.width + dw, height: node.height + dh }, 'Grootte');
        }
    });
}

export function boxOf(editor: Editor, id: string): Box | null {
    const node = editor.graph.getNode(id);
    return node ? { x: Math.round(node.x), y: Math.round(node.y), width: Math.round(node.width), height: Math.round(node.height) } : null;
}
