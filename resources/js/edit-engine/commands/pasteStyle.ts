import type { SceneGraph } from '@open-pencil/scene-graph';
import type { DrawnCanvas } from '../../layout';
import type { CopiedStyle } from '../../layout/styleScope';
import type { LayoutStyle } from '../DrawingEngine';
import type { Selection } from '../transform';
import { setStyle, storedStyle, type StyleChange, type StyleHooks, type StylePatch } from './style';

export { canPaste, type CopiedStyle } from '../../layout/styleScope';

export function copyStyle(graph: SceneGraph, canvas: DrawnCanvas, id: string): CopiedStyle | null {
    const block = canvas.blocks.find((candidate) => candidate.id === id);
    return block && block.areaKey !== 'direction' ? { kind: block.kind, style: storedStyle(graph, id) } : null;
}

export function replacementPatch(current: LayoutStyle, style: LayoutStyle): StylePatch {
    return { ...Object.fromEntries(Object.keys(current).map((key) => [key, null])), ...style };
}

export function pasteStyle(selection: Selection, canvas: DrawnCanvas, copied: CopiedStyle, hooks: StyleHooks = {}): StyleChange {
    const kinds = new Map(canvas.blocks.map((block) => [block.id, block.kind]));
    const ids = selection.ids.filter((id) => kinds.get(id) === copied.kind);
    let result: StyleChange = { ids: [], autoText: [] };
    selection.editor.undo.runBatch('Paste style', () => {
        for (const id of ids) {
            const next = setStyle({ ...selection, ids: [id] }, canvas, replacementPatch(storedStyle(selection.editor.graph, id), copied.style), hooks);
            result = { ids: [...result.ids, ...next.ids], autoText: [...result.autoText, ...next.autoText] };
        }
    });
    return result;
}
