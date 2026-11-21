import { describe, expect, it } from 'vitest';
import { setStyle, storedStyle, type StylePatch } from '../../../resources/js/edit-engine/commands/style';
import { TEXT_SUFFIX } from '../../../resources/js/edit-engine/scene';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [element('d', 'primary_activities', 'domain', 'Domein'), element('a', 'primary_activities', 'activity', 'Activiteit', 'd', 1)];
const layouts = { primary_activities: manual(0, 200, 900, 500, 0), d: manual(16, 48, 600, 400), a: manual(10, 40, 200, 60) };
const light = { fill: '#FFFFFF', text_colour: '#171B21' };

const run = (before: Record<string, Record<string, string | number>>, ids: string[], patch: StylePatch) => {
    const view = open(tree, layouts);
    for (const [id, style] of Object.entries(before)) view.restrictions.privileged(() => setStyle(view.selection([id]), view.canvas, style, { measurer: fakeMeasurer }));
    const result = view.restrictions.privileged(() => setStyle(view.selection(ids), view.canvas, patch, { measurer: fakeMeasurer }));
    return { ...view, result };
};

describe('automatic text colour', () => {
    it('returns dark own text to automatic white when the fill turns dark, in the same action', () => {
        const { graph, result } = run({ d: light }, ['d'], { fill: '#000000' });
        expect(result.autoText).toEqual(['d']);
        expect(storedStyle(graph, 'd')).toEqual({ fill: '#000000' });
        expect(graph.getNode(`d${TEXT_SUFFIX}`)!.fills[0].color).toMatchObject({ r: 1, g: 1, b: 1 });
    });

    it('keeps own text that still reads on the new fill', () => {
        const { graph, result } = run({ d: light }, ['d'], { fill: '#FFFF00' });
        expect(result.autoText).toEqual([]);
        expect(storedStyle(graph, 'd').text_colour).toBe('#171B21');
    });

    it('counts a fill opacity change over the fill behind', () => {
        const { result } = run({ d: { fill: '#000000' }, a: { fill: '#FFFFFF', text_colour: '#171B21' } }, ['a'], { fill_opacity: 0 });
        expect(result.autoText).toEqual(['a']);
    });

    it('returns the own text of a child whose backdrop turned dark', () => {
        const { graph, result } = run({ d: { fill: '#FFFFFF' }, a: { fill_opacity: 0, text_colour: '#171B21' } }, ['d'], { fill: '#000000' });
        expect(result.autoText).toEqual(['a']);
        expect(storedStyle(graph, 'a').text_colour).toBeUndefined();
    });

    it('leaves Elements without an own text colour alone', () => {
        const { result } = run({}, ['d'], { fill: '#000000' });
        expect(result.autoText).toEqual([]);
    });

    it('undoes the fill and the text colour together', () => {
        const { graph, editor } = run({ d: light }, ['d'], { fill: '#000000' });
        editor.undoAction();
        expect(storedStyle(graph, 'd')).toEqual(light);
    });
});
