import { defaultColours } from '../colour';
import { styleBackdrop } from './resolveStyle';
import type { BlockStyle, DrawnBlock } from './types';

export function behindOf(block: DrawnBlock, blocks: Map<string, DrawnBlock>, styleOf: (id: string) => BlockStyle): string {
    const parent = block.kind === 'area' ? undefined : blocks.get(block.parentId ?? block.areaKey);
    return parent ? backdropOf(parent, blocks, styleOf) : defaultColours.white;
}

export const backdropOf = (block: DrawnBlock, blocks: Map<string, DrawnBlock>, styleOf: (id: string) => BlockStyle): string => styleBackdrop(styleOf(block.id), behindOf(block, blocks, styleOf));
