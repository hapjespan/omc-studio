import { describe, expect, it } from 'vitest';
import { fitZoom, zoomIn, zoomOut } from '../../../resources/js/canvas/ZoomToolbar.vue';

describe('zoom steps', () => {
    it('moves in steps of 10 between 50 and 200', () => {
        expect(zoomIn(100)).toBe(110);
        expect(zoomOut(100)).toBe(90);
        expect(zoomIn(200)).toBe(200);
        expect(zoomOut(50)).toBe(50);
        expect(zoomOut(60)).toBe(50);
        expect(zoomIn(190)).toBe(200);
    });

    it('snaps to the next step from a fitted zoom', () => {
        expect(zoomIn(37)).toBe(40);
        expect(zoomOut(37)).toBe(37);
        expect(zoomOut(54)).toBe(50);
        expect(zoomIn(123)).toBe(130);
        expect(zoomOut(123)).toBe(120);
    });
});

describe('fitZoom', () => {
    const content = { width: 1680, height: 1188 };

    it('fits the whole Canvas in the viewport', () => {
        expect(fitZoom({ width: 840, height: 2000 }, content)).toBe(50);
        expect(fitZoom({ width: 3000, height: 594 }, content)).toBe(50);
    });

    it('stays within 10 and 200 percent', () => {
        expect(fitZoom({ width: 100, height: 100 }, content)).toBe(10);
        expect(fitZoom({ width: 9000, height: 9000 }, content)).toBe(200);
    });

    it('uses the taller content when it spills below the Artboard', () => {
        expect(fitZoom({ width: 1680, height: 1000 }, { width: 1680, height: 2000 })).toBe(50);
    });
});
