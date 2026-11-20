import type { DrawnBlock, DrawnCanvas } from '../../layout';
import type { Selection } from '../transform';
import { pasteStyle } from './pasteStyle';
import { storedStyle, type StyleChange, type StyleHooks } from './style';

export interface ApplyScope {
    scope: 'domains' | 'activities';
    ids: string[];
}

const parentOf = (block: DrawnBlock, blocks: Map<string, DrawnBlock>): DrawnBlock | undefined => blocks.get(block.parentId ?? '');

function domainOf(block: DrawnBlock, blocks: Map<string, DrawnBlock>): DrawnBlock | undefined {
    for (let current = parentOf(block, blocks); current; current = parentOf(current, blocks)) if (current.kind === 'domain') return current;
    return undefined;
}

export function applyScope(canvas: DrawnCanvas, id: string): ApplyScope | null {
    const blocks = new Map(canvas.blocks.map((block) => [block.id, block]));
    const block = blocks.get(id);
    if (!block || block.system) return null;
    if (block.kind === 'domain') return { scope: 'domains', ids: canvas.blocks.filter((other) => other.kind === 'domain' && other.areaKey === block.areaKey && other.id !== id && !other.system).map((other) => other.id) };
    if (block.kind !== 'activity') return null;
    const domain = domainOf(block, blocks);
    return domain ? { scope: 'activities', ids: canvas.blocks.filter((other) => other.kind === 'activity' && other.id !== id && domainOf(other, blocks)?.id === domain.id).map((other) => other.id) } : null;
}

export function applyStyleToSiblings(selection: Selection, canvas: DrawnCanvas, id: string, hooks: StyleHooks = {}): StyleChange {
    const found = applyScope(canvas, id);
    const block = canvas.blocks.find((candidate) => candidate.id === id);
    if (!found || !block) return { ids: [], autoText: [] };
    return pasteStyle({ ...selection, ids: found.ids }, canvas, { kind: block.kind, style: storedStyle(selection.editor.graph, id) }, hooks);
}
