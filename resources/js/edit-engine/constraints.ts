export interface Box {
    x: number;
    y: number;
    width: number;
    height: number;
}

export type Container = { kind: 'artboard-area' } | { kind: 'parent'; width: number; height: number };

export interface Constrained {
    changes: Partial<Box>;
    pushed: boolean;
    growParentTo: number | null;
}

export const MIN_WIDTH = 40;
export const MIN_HEIGHT = 24;
export const ARTBOARD_WIDTH = 1680;
export const ARTBOARD_HEIGHT = 1188;
export const DIRECTION_HEIGHT = 180;

const BOX_KEYS: (keyof Box)[] = ['x', 'y', 'width', 'height'];

const clamp = (value: number, low: number, high: number): number => Math.min(Math.max(value, low), Math.max(low, high));

export function constrain(current: Box, changes: Partial<Box>, container: Container, childRight: number): Constrained {
    const touched = BOX_KEYS.filter((key) => key in changes);
    if (touched.length === 0) return { changes, pushed: false, growParentTo: null };

    const wanted = { ...current, ...changes };
    const maxWidth = container.kind === 'parent' ? container.width : ARTBOARD_WIDTH;
    const width = clamp(wanted.width, Math.max(MIN_WIDTH, childRight), maxWidth);
    const height = container.kind === 'parent' ? Math.max(wanted.height, MIN_HEIGHT) : clamp(wanted.height, MIN_HEIGHT, ARTBOARD_HEIGHT - DIRECTION_HEIGHT);
    const x = clamp(wanted.x, 0, maxWidth - width);
    const lowest = container.kind === 'parent' ? 0 : DIRECTION_HEIGHT;
    const y = container.kind === 'parent' ? Math.max(wanted.y, lowest) : clamp(wanted.y, lowest, ARTBOARD_HEIGHT - height);
    const result: Box = { x, y, width, height };
    const next: Partial<Box> = {};
    for (const key of touched) next[key] = result[key];
    if (touched.includes('width') && !('x' in next)) next.x = result.x;
    if (touched.includes('height') && !('y' in next)) next.y = result.y;
    const pushed = (['x', 'y'] as const).some((key) => key in changes && result[key] !== changes[key]) || (['width', 'height'] as const).some((key) => key in changes && result[key] !== changes[key]);

    return { changes: next, pushed, growParentTo: container.kind === 'parent' && y + height > container.height ? y + height : null };
}
