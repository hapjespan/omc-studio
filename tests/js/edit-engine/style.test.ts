import { describe, expect, it } from 'vitest';
import { mergeStyle, setStyle, storedStyle } from '../../../resources/js/edit-engine/commands/style';
import { TEXT_SUFFIX } from '../../../resources/js/edit-engine/scene';
import { boxOf } from '../../../resources/js/edit-engine/transform';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [element('d', 'primary_activities', 'domain', 'Domein'), element('a', 'primary_activities', 'activity', 'Een lange activiteit naam', 'd', 1)];
const layouts = { primary_activities: manual(0, 200, 900, 500, 0), d: manual(16, 48, 600, 400), a: manual(10, 40, 200, 60) };

const run = (patch: Parameters<typeof setStyle>[2], ids = ['d']) => {
    const view = open(tree, layouts);
    const result = view.restrictions.privileged(() => setStyle(view.selection(ids), view.canvas, patch, { measurer: fakeMeasurer }));
    return { ...view, result };
};

describe('mergeStyle', () => {
    it('adds values and removes a key set to null', () => {
        expect(mergeStyle({ fill: '#112233', italic: true }, { fill: '#445566', italic: null })).toEqual({ fill: '#445566' });
    });
});

describe('setStyle', () => {
    it('paints the fill, border, corners and text and keeps the style on the node', () => {
        const { graph } = run({ fill: '#112233', fill_opacity: 40, border_colour: '#445566', border_width: 4, border_style: 'dashed', corner_radius: 12, text_colour: '#FFFFFF', font: 'inter', font_size: 20, italic: true, align_horizontal: 'center' });
        const frame = graph.getNode('d')!;
        expect(frame.fills[0]).toMatchObject({ opacity: 0.4 });
        expect(frame.strokes[0]).toMatchObject({ weight: 4, dashPattern: [12, 8] });
        expect(frame.cornerRadius).toBe(12);
        const text = graph.getNode(`d${TEXT_SUFFIX}`)!;
        expect(text).toMatchObject({ fontSize: 20, italic: true, textAlignHorizontal: 'CENTER' });
        expect(storedStyle(graph, 'd')).toMatchObject({ fill: '#112233', font_size: 20 });
    });

    it('leaves places and sizes of the styled Element unchanged', () => {
        const before = boxOf(open(tree, layouts).editor, 'd');
        const { editor } = run({ fill: '#112233' });
        expect(boxOf(editor, 'd')).toEqual(before);
    });

    it('styles every selected Element in one undo step', () => {
        const { editor, graph } = run({ fill: '#112233' }, ['d', 'a']);
        expect(storedStyle(graph, 'a').fill).toBe('#112233');
        editor.undoAction();
        expect(storedStyle(graph, 'a')).toEqual({});
        expect(storedStyle(graph, 'd')).toEqual({});
    });

    it('rewraps the text lines for a larger font', () => {
        const { graph } = run({ font_size: 40 }, ['a']);
        expect(graph.getNode(`a${TEXT_SUFFIX}`)!.text.split('\n').length).toBeGreaterThan(1);
    });

    it('ignores the Direction band and unknown ids', () => {
        const { result } = run({ fill: '#112233' }, ['direction', 'nope']);
        expect(result.ids).toEqual([]);
    });
});
