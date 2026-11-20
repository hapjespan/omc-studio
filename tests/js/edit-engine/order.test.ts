import { describe, expect, it } from 'vitest';
import { backward, forward, stackOrders } from '../../../resources/js/edit-engine/commands/order';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [element('d', 'primary_activities', 'domain', 'Domein'), element('a', 'primary_activities', 'activity', 'Een', 'd', 1), element('b', 'primary_activities', 'activity', 'Twee', 'd', 2), element('c', 'primary_activities', 'activity', 'Drie', 'd', 3)];
const layouts = { d: manual(16, 48, 600, 400), a: manual(10, 40, 100, 30, 0), b: manual(60, 100, 120, 40, 1), c: manual(200, 300, 80, 50, 2) };
const blocks = (editor: ReturnType<typeof open>['editor']) => (editor.graph.getNode('d')!.childIds ?? []).filter((id) => !id.endsWith('#text'));

describe('order commands', () => {
    it('moves one step in front of and behind its siblings, with the saved order following', () => {
        const { editor, selection } = open(tree, layouts);
        const before = blocks(editor);
        editor.select(['a']);
        forward(selection(['a']));
        expect(blocks(editor)).toEqual([before[1], before[0], before[2]]);
        expect([...stackOrders(editor, ['d'], (id) => id.endsWith('#text'))]).toEqual([[before[1], 0], [before[0], 1], [before[2], 2]]);
        backward(selection(['a']));
        expect(blocks(editor)).toEqual(before);
    });

    it('does nothing without a selection', () => {
        const { editor, selection } = open(tree, layouts);
        const before = blocks(editor);
        forward(selection([]));
        expect(blocks(editor)).toEqual(before);
    });
});
