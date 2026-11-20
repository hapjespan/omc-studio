import type { Selection } from '../transform';

export type Axis = 'horizontal' | 'vertical';
export type Edge = 'start' | 'centre' | 'end';

const EDGES = { start: 'min', centre: 'center', end: 'max' } as const;

export function align({ editor, ids }: Selection, axis: Axis, to: Edge): void {
    if (ids.length > 0) editor.alignNodes(ids, axis, EDGES[to]);
}

export function distribute({ editor, ids }: Selection, axis: Axis): void {
    if (ids.length >= 3) editor.distributeNodes(ids, axis);
}

export function equalSize({ editor, ids }: Selection, key: 'width' | 'height'): void {
    const [first, ...others] = ids;
    const reference = first === undefined ? undefined : editor.graph.getNode(first);
    if (!reference || others.length === 0) return;
    editor.undo.runBatch(key === 'width' ? 'Gelijke breedte' : 'Gelijke hoogte', () => {
        for (const id of others) editor.updateNodeWithUndo(id, { [key]: reference[key] }, key === 'width' ? 'Gelijke breedte' : 'Gelijke hoogte');
    });
}
