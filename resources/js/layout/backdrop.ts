import { defaultColours, effectiveFill } from '../colour';
import type { BlockStyle, DrawnBlock } from './types';

export function backdropOf(block: DrawnBlock, blocks: Map<string, DrawnBlock>, styleOf: (id: string) => BlockStyle): string {
    const parent = block.kind === 'area' ? undefined : blocks.get(block.parentId ?? block.areaKey);
    const behind = parent ? backdropOf(parent, blocks, styleOf) : defaultColours.white;
    const { fill, fillOpacity } = styleOf(block.id);
    return fill === null ? behind : effectiveFill(fill, fillOpacity, behind);
}
