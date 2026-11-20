import { describe, expect, it } from 'vitest';
import { setBox, nudge, resizeBy, boxOf } from '../../../resources/js/edit-engine/transform';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [
    element('d', 'primary_activities', 'domain', 'Domein'),
    element('k', 'primary_activities', 'cluster', 'Cluster', 'd', 1),
    element('a', 'primary_activities', 'activity', 'Les', 'k', 1),
    element('b', 'primary_activities', 'activity', 'Les twee', 'k', 2),
];
const layouts = { primary_activities: manual(0, 200, 900, 500, 0), d: manual(16, 48, 400, 300), k: manual(8, 40, 300, 200), a: manual(8, 40, 100, 30), b: manual(120, 40, 100, 30) };

describe('push back and growth', () => {
    it('pushes a child inside its parent and reports it', () => {
        const pushed: string[] = [];
        const { editor } = open(tree, layouts, { onPushBack: (id) => pushed.push(id) });
        setBox(editor, 'a', { x: -50, y: -10 });
        expect([boxOf(editor, 'a')!.x, boxOf(editor, 'a')!.y]).toEqual([0, 0]);
        expect(pushed).toContain('a');
    });

    it('grows the parents, up the chain, for a child dragged below them', () => {
        const { editor } = open(tree, layouts);
        const before = [boxOf(editor, 'k')!.height, boxOf(editor, 'd')!.height];
        setBox(editor, 'a', { y: 400 });
        expect(boxOf(editor, 'k')!.height).toBeGreaterThanOrEqual(430);
        expect(boxOf(editor, 'd')!.height).toBeGreaterThan(before[1]);
        expect(boxOf(editor, 'k')!.height).toBeGreaterThan(before[0]);
    });

    it('pushes an Area off the Direction band and the Artboard back', () => {
        const pushed: string[] = [];
        const { editor } = open(tree, layouts, { onPushBack: (id) => pushed.push(id) });
        setBox(editor, 'primary_activities', { y: 20 });
        expect(boxOf(editor, 'primary_activities')!.y).toBe(180);
        setBox(editor, 'primary_activities', { x: 1600 });
        expect(boxOf(editor, 'primary_activities')!.x).toBe(780);
        expect(pushed).toEqual(['primary_activities', 'primary_activities']);
    });

    it('moves a Cluster with its Activities as a whole', () => {
        const { editor, selection } = open(tree, layouts);
        const before = boxOf(editor, 'a')!;
        nudge(selection(['k']), 20, 10);
        expect(boxOf(editor, 'k')!.x).toBe(28);
        expect(boxOf(editor, 'a')).toEqual(before);
    });

    it('stops width and height at their limits and keeps the width above the widest child', () => {
        const { editor, selection } = open(tree, layouts);
        resizeBy(selection(['b']), -500, -500);
        expect(boxOf(editor, 'b')).toMatchObject({ width: 40, height: 24 });
        setBox(editor, 'k', { width: 50 });
        expect(boxOf(editor, 'k')!.width).toBeGreaterThanOrEqual(120 + 40);
    });

    it('undoes a nudge in one step', () => {
        const { editor, selection } = open(tree, layouts);
        nudge(selection(['a', 'b']), 10, 0);
        expect(boxOf(editor, 'a')!.x).toBe(18);
        editor.undoAction();
        expect([boxOf(editor, 'a')!.x, boxOf(editor, 'b')!.x]).toEqual([8, 120]);
    });
});
