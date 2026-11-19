import { describe, expect, it } from 'vitest';
import { PNG_SCALE, pngFileName } from '../../../resources/js/canvas/exportPng';

describe('exportPng', () => {
    it('names the file from neutral words and the date', () => {
        expect(pngFileName(new Date(2026, 10, 19))).toBe('canvas-2026-11-19.png');
    });

    it('renders at twice the Artboard size', () => {
        expect(PNG_SCALE).toBe(2);
    });
});
