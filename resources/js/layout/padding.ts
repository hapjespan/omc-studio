import { isListArea } from './arrangeAreas';
import type { BlockKind, DrawnBlock, ElementKind } from './types';

export const MODALITY_BADGE = 22;

export const elementPadding: Record<ElementKind, { x: number; y: number }> = {
    domain: { x: 12, y: 12 },
    cluster: { x: 8, y: 8 },
    activity: { x: 8, y: 6 },
};

const areaPadding = { x: 12, y: 5 };

export function blockPadding(block: Pick<DrawnBlock, 'kind' | 'areaKey'>): { x: number; y: number } {
    const kind: BlockKind = block.kind;
    if (kind === 'area') return areaPadding;
    return isListArea(block.areaKey) ? { x: 0, y: 0 } : elementPadding[kind];
}
