import { describe, expect, it } from 'vitest';
import { canPaste, copyStyle, pasteStyle } from '../../../resources/js/edit-engine/commands/pasteStyle';
import { setStyle, storedStyle } from '../../../resources/js/edit-engine/commands/style';
import { boxOf } from '../../../resources/js/edit-engine/transform';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [
    element('d1', 'primary_activities', 'domain', 'Een'),
    element('d2', 'primary_activities', 'domain', 'Twee', null, 2),
    element('a', 'primary_activities', 'activity', 'Activiteit', 'd1', 1),
];
const layouts = { primary_activities: manual(0, 200, 900, 500, 0), d1: manual(16, 48, 300, 200), d2: manual(330, 48, 300, 200), a: manual(10, 40, 100, 40) };

const view = () => {
    const opened = open(tree, layouts);
    const apply = (ids: string[], patch: Parameters<typeof setStyle>[2]) => opened.restrictions.privileged(() => setStyle(opened.selection(ids), opened.canvas, patch, { measurer: fakeMeasurer }));
    return { ...opened, apply };
};

describe('copy and paste style', () => {
    it('copies the kind and the stored style of an Element', () => {
        const { apply, graph, canvas } = view();
        apply(['d1'], { fill: '#112233', corner_radius: 3 });
        expect(copyStyle(graph, canvas, 'd1')).toEqual({ kind: 'domain', style: { fill: '#112233', corner_radius: 3 } });
        expect(copyStyle(graph, canvas, 'nope')).toBeNull();
    });

    it('pastes the whole style onto another Domain and drops what the target had besides', () => {
        const { apply, graph, canvas, selection, restrictions, editor } = view();
        apply(['d1'], { fill: '#112233' });
        apply(['d2'], { border_width: 4 });
        const before = boxOf(editor, 'd2');
        const result = restrictions.privileged(() => pasteStyle(selection(['d2']), canvas, copyStyle(graph, canvas, 'd1')!, { measurer: fakeMeasurer }));
        expect(result.ids).toEqual(['d2']);
        expect(storedStyle(graph, 'd2')).toEqual({ fill: '#112233' });
        expect(boxOf(editor, 'd2')).toEqual(before);
    });

    it('does not offer or apply a paste onto another kind', () => {
        const { apply, graph, canvas, selection, restrictions } = view();
        apply(['d1'], { fill: '#112233' });
        const copied = copyStyle(graph, canvas, 'd1')!;
        expect(canPaste(copied, 'activity')).toBe(false);
        expect(canPaste(copied, 'domain')).toBe(true);
        expect(canPaste(null, 'domain')).toBe(false);
        const result = restrictions.privileged(() => pasteStyle(selection(['a']), canvas, copied, { measurer: fakeMeasurer }));
        expect(result.ids).toEqual([]);
        expect(storedStyle(graph, 'a')).toEqual({});
    });
});
