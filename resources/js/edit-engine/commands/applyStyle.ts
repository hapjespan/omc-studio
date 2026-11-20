import type { DrawnCanvas } from '../../layout';
import { applyScope } from '../../layout/styleScope';
import type { Selection } from '../transform';
import { pasteStyle } from './pasteStyle';
import { storedStyle, type StyleChange, type StyleHooks } from './style';

export { applyScope, type ApplyScope } from '../../layout/styleScope';

export function applyStyleToSiblings(selection: Selection, canvas: DrawnCanvas, id: string, hooks: StyleHooks = {}): StyleChange {
    const found = applyScope(canvas, id);
    const block = canvas.blocks.find((candidate) => candidate.id === id);
    if (!found || !block) return { ids: [], autoText: [] };
    return pasteStyle({ ...selection, ids: found.ids }, canvas, { kind: block.kind, style: storedStyle(selection.editor.graph, id) }, hooks);
}
