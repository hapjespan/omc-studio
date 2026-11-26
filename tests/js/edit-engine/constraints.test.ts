import { describe, expect, it } from 'vitest';
import { constrain, type Box } from '../../../resources/js/edit-engine/constraints';

const box: Box = { x: 10, y: 20, width: 100, height: 60 };
const parent = { kind: 'parent' as const, width: 300, height: 200 };
const area = { kind: 'artboard-area' as const };

describe('constrain inside a parent', () => {
    it('lets a move inside through untouched', () => {
        expect(constrain(box, { x: 50, y: 30 }, parent, 0)).toEqual({ changes: { x: 50, y: 30 }, pushed: false, growParentTo: null });
    });

    it('pushes left, right and top back inside and reports it', () => {
        expect(constrain(box, { x: -40 }, parent, 0).changes).toEqual({ x: 0 });
        expect(constrain(box, { x: 900 }, parent, 0).changes).toEqual({ x: 200 });
        const top = constrain(box, { y: -30 }, parent, 0);
        expect(top.changes).toEqual({ y: 0 });
        expect(top.pushed).toBe(true);
    });

    it('grows the parent for a child below it instead of pushing', () => {
        const below = constrain(box, { y: 180 }, parent, 0);
        expect(below.changes).toEqual({ y: 180 });
        expect(below.pushed).toBe(false);
        expect(below.growParentTo).toBe(240);
    });

    it('stops width and height at the minimum of 40 by 24', () => {
        expect(constrain(box, { width: 10, height: 5 }, parent, 0).changes).toMatchObject({ width: 40, height: 24 });
    });

    it('keeps the width at least the largest child x plus width', () => {
        expect(constrain(box, { width: 50 }, parent, 90).changes).toMatchObject({ width: 90 });
    });

    it('caps the width at the parent and keeps the box inside', () => {
        expect(constrain(box, { width: 5000 }, parent, 0).changes).toMatchObject({ width: 300, x: 0 });
    });
});

describe('constrain an Area on the Artboard', () => {
    const place: Box = { x: 12, y: 200, width: 800, height: 400 };

    it('pushes an Area off the Direction band and the Artboard edges back', () => {
        expect(constrain(place, { y: 50 }, area, 0).changes).toEqual({ y: 180 });
        expect(constrain(place, { x: 1500 }, area, 0).changes).toEqual({ x: 880 });
        expect(constrain(place, { y: 1100 }, area, 0).changes).toEqual({ y: 788 });
    });

    it('never grows an Artboard parent', () => {
        expect(constrain(place, { y: 1100 }, area, 0).growParentTo).toBeNull();
    });
});
