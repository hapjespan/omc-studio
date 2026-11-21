import type { Editor } from '@open-pencil/core/editor';
import type { Box } from './constraints';

export interface Selection {
    editor: Editor;
    ids: string[];
}

export function setBox(editor: Editor, id: string, box: Partial<Box>): void {
    editor.updateNodeWithUndo(id, box, 'Box');
}

export function nudge({ editor, ids }: Selection, dx: number, dy: number): void {
    editor.undo.runBatch('Move', () => {
        for (const id of ids) {
            const node = editor.graph.getNode(id);
            if (node) editor.updateNodeWithUndo(id, { x: node.x + dx, y: node.y + dy }, 'Move');
        }
    });
}

export function resizeBy({ editor, ids }: Selection, dw: number, dh: number): void {
    editor.undo.runBatch('Resize', () => {
        for (const id of ids) {
            const node = editor.graph.getNode(id);
            if (node) editor.updateNodeWithUndo(id, { ...(dw === 0 ? {} : { width: node.width + dw }), ...(dh === 0 ? {} : { height: node.height + dh }) }, 'Resize');
        }
    });
}

export function boxOf(editor: Editor, id: string): Box | null {
    const node = editor.graph.getNode(id);
    return node ? { x: Math.round(node.x), y: Math.round(node.y), width: Math.round(node.width), height: Math.round(node.height) } : null;
}
