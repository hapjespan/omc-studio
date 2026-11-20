import { describe, expect, it } from 'vitest';
import { commandForKey, type KeyInput } from '../../../resources/js/edit-engine/keyboard';

const press = (key: string, extra: Partial<KeyInput> = {}) => commandForKey({ key, shiftKey: false, altKey: false, ctrlKey: false, metaKey: false, ...extra });

describe('keyboard model', () => {
    it('moves 1 pixel, 8 with Shift', () => {
        expect(press('ArrowRight')).toEqual({ kind: 'nudge', dx: 1, dy: 0 });
        expect(press('ArrowUp', { shiftKey: true })).toEqual({ kind: 'nudge', dx: 0, dy: -8 });
    });

    it('resizes with Alt and arrows, also with Shift', () => {
        expect(press('ArrowRight', { altKey: true })).toEqual({ kind: 'resizeBy', dw: 1, dh: 0 });
        expect(press('ArrowDown', { altKey: true, shiftKey: true })).toEqual({ kind: 'resizeBy', dw: 0, dh: 8 });
    });

    it('maps undo and redo', () => {
        expect(press('z', { ctrlKey: true })).toEqual({ kind: 'undo' });
        expect(press('Z', { ctrlKey: true, shiftKey: true })).toEqual({ kind: 'redo' });
        expect(press('z', { metaKey: true })).toEqual({ kind: 'undo' });
    });

    it('ignores other keys', () => {
        expect(press('a')).toBeNull();
        expect(press('ArrowLeft', { ctrlKey: true })).toBeNull();
    });
});
