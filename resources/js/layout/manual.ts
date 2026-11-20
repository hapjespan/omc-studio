import type { ElementLayout, ManualRect } from './types';

export function manualRect(layout: ElementLayout | undefined): ManualRect | null {
    if (layout?.mode !== 'manual') return null;
    const { x, y, width, height, z } = layout;
    return x === undefined || y === undefined || width === undefined || height === undefined || z === undefined ? null : { x, y, width, height, z };
}
