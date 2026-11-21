import { backdropOf, behindOf } from '../../layout/backdrop';
import { resolveStyle } from '../../layout/resolveStyle';
import type { BlockStyle, DrawnCanvas, ElementStyle } from '../../layout';
import type { DrawingEngine } from '../../edit-engine';
import type { StyleTarget } from './StylePanel.vue';

export function styleTargetOf(drawn: DrawnCanvas | null, id: string | null, live: DrawingEngine | null): StyleTarget | null {
    const blocks = new Map((drawn?.blocks ?? []).map((candidate) => [candidate.id, candidate]));
    const block = id ? blocks.get(id) : undefined;
    const styleOf = (key: string): BlockStyle => resolveStyle(blocks.get(key)!.areaKey, blocks.get(key)!.kind, (live?.getStyle(key) ?? {}) as ElementStyle);
    if (!block || block.areaKey === 'direction' || !live) return null;

    return { kind: block.kind, resolved: resolveStyle(block.areaKey, block.kind, (live.getStyle(block.id) ?? {}) as ElementStyle, behindOf(block, blocks, styleOf)), backdrop: backdropOf(block, blocks, styleOf) };
}
