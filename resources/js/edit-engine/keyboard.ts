import type { DrawingCommand } from './DrawingEngine';

export interface KeyInput {
    key: string;
    shiftKey: boolean;
    altKey: boolean;
    ctrlKey: boolean;
    metaKey: boolean;
}

export type KeyCommand = DrawingCommand | { kind: 'undo' } | { kind: 'redo' };

const DIRECTIONS: Record<string, [number, number]> = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

export function commandForKey(input: KeyInput): KeyCommand | null {
    const modifier = input.ctrlKey || input.metaKey;
    if (modifier && input.key.toLowerCase() === 'z') return input.shiftKey ? { kind: 'redo' } : { kind: 'undo' };
    if (modifier && input.key.toLowerCase() === 'y') return { kind: 'redo' };
    const direction = DIRECTIONS[input.key];
    if (!direction || modifier) return null;
    const step = input.shiftKey ? 8 : 1;
    const [dx, dy] = [direction[0] * step, direction[1] * step];

    return input.altKey ? { kind: 'resizeBy', dw: dx, dh: dy } : { kind: 'nudge', dx, dy };
}
