import { describe, expect, it } from 'vitest';
import { align, distribute, equalSize } from '../../../resources/js/edit-engine/commands/align';
import { boxOf } from '../../../resources/js/edit-engine/transform';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [
    element('d', 'primary_activities', 'domain', 'Domein'),
    element('a', 'primary_activities', 'activity', 'Een', 'd', 1),
    element('b', 'primary_activities', 'activity', 'Twee', 'd', 2),
    element('c', 'primary_activities', 'activity', 'Drie', 'd', 3),
];
const layouts = { primary_activities: manual(0, 200, 900, 500, 0), d: manual(16, 48, 600, 400), a: manual(10, 40, 100, 30), b: manual(60, 100, 120, 40), c: manual(200, 300, 80, 50) };

describe('alignment commands', () => {
    it('aligns three Activities left and distributes them vertically, undone in one step', () => {
        const { editor, selection } = open(tree, layouts);
        const ids = ['a', 'b', 'c'];
        align(selection(ids), 'horizontal', 'start');
        expect(new Set(ids.map((id) => boxOf(editor, id)!.x)).size).toBe(1);
        const before = ids.map((id) => boxOf(editor, id)!.y);
        distribute(selection(ids), 'vertical');
        const [a, b, c] = ids.map((id) => boxOf(editor, id)!);
        expect(b.y - (a.y + a.height)).toBe(c.y - (b.y + b.height));
        editor.undoAction();
        expect(ids.map((id) => boxOf(editor, id)!.y)).toEqual(before);
    });

    it('needs three items to distribute', () => {
        const { editor, selection } = open(tree, layouts);
        const before = boxOf(editor, 'b');
        distribute(selection(['a', 'b']), 'vertical');
        expect(boxOf(editor, 'b')).toEqual(before);
    });

    it('gives equal width and height following the first selected, in one undo step', () => {
        const { editor, selection } = open(tree, layouts);
        equalSize(selection(['b', 'a', 'c']), 'width');
        equalSize(selection(['b', 'a', 'c']), 'height');
        expect(['a', 'c'].map((id) => [boxOf(editor, id)!.width, boxOf(editor, id)!.height])).toEqual([[120, 40], [120, 40]]);
        editor.undoAction();
        expect(['a', 'c'].map((id) => boxOf(editor, id)!.height)).toEqual([30, 50]);
    });

    it('does nothing for equal size with one selected', () => {
        const { editor, selection } = open(tree, layouts);
        equalSize(selection(['a']), 'width');
        expect(boxOf(editor, 'a')!.width).toBe(100);
    });
});
