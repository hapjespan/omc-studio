import { describe, expect, it } from 'vitest';
import { snapChanges, snapToGrid } from '../../../resources/js/edit-engine/commands/grid';
import { boxOf } from '../../../resources/js/edit-engine/transform';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [element('d', 'primary_activities', 'domain', 'Domein'), element('a', 'primary_activities', 'activity', 'Een', 'd', 1)];
const layouts = { d: manual(16, 48, 600, 400), a: manual(10, 40, 100, 30) };

describe('grid', () => {
    it('rounds to multiples of 10', () => {
        expect([snapToGrid(3), snapToGrid(5), snapToGrid(12), snapToGrid(-6)]).toEqual([0, 10, 10, -10]);
        expect(snapChanges({ x: 13, width: 101 })).toEqual({ x: 10, width: 100 });
    });

    it('snaps place and size with the grid on and leaves them off', () => {
        let on = true;
        const { editor } = open(tree, layouts, { snap: () => on });
        editor.graph.updateNodePreview('a', { x: 13, y: 21, width: 101, height: 45 });
        expect(boxOf(editor, 'a')).toEqual({ x: 10, y: 20, width: 100, height: 50 });
        on = false;
        editor.graph.updateNodePreview('a', { x: 13, width: 101 });
        expect(boxOf(editor, 'a')).toMatchObject({ x: 13, width: 101 });
    });
});
