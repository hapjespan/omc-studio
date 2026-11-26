import { describe, expect, it } from 'vitest';
import { applyScope, applyStyleToSiblings } from '../../../resources/js/edit-engine/commands/applyStyle';
import { setStyle, storedStyle } from '../../../resources/js/edit-engine/commands/style';
import { boxOf } from '../../../resources/js/edit-engine/transform';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [
    element('d1', 'primary_activities', 'domain', 'Een'),
    element('d2', 'primary_activities', 'domain', 'Twee', null, 2),
    element('d3', 'supporting_activities', 'domain', 'Drie'),
    element('k', 'primary_activities', 'cluster', 'Cluster', 'd1', 1),
    element('a1', 'primary_activities', 'activity', 'Een', 'k', 1),
    element('a2', 'primary_activities', 'activity', 'Twee', 'd1', 2),
    element('a3', 'primary_activities', 'activity', 'Drie', 'd2', 1),
];
const layouts = {
    primary_activities: manual(0, 200, 900, 500, 0),
    supporting_activities: manual(0, 720, 900, 300, 0),
    d1: manual(16, 48, 300, 300),
    d2: manual(330, 48, 300, 200),
    d3: manual(16, 48, 300, 100),
    k: manual(8, 40, 250, 120),
    a1: manual(8, 30, 200, 40),
    a2: manual(10, 200, 100, 40),
    a3: manual(10, 40, 100, 40),
};

const view = () => {
    const opened = open(tree, layouts);
    const apply = (ids: string[], patch: Parameters<typeof setStyle>[2]) => opened.restrictions.privileged(() => setStyle(opened.selection(ids), opened.canvas, patch, { measurer: fakeMeasurer }));
    return { ...opened, apply };
};

describe('applyScope', () => {
    it('names the Domains of the same Area for a Domain', () => {
        const { canvas } = view();
        expect(applyScope(canvas, 'd1')).toEqual({ scope: 'domains', ids: ['d2'] });
    });

    it('names the other Activities in the same Domain, also inside Clusters, for an Activity', () => {
        const { canvas } = view();
        expect(applyScope(canvas, 'a2')).toEqual({ scope: 'activities', ids: expect.arrayContaining(['a1']) });
        expect(applyScope(canvas, 'a2')?.ids).toHaveLength(1);
    });

    it('offers nothing for a Cluster or an Area', () => {
        const { canvas } = view();
        expect(applyScope(canvas, 'k')).toBeNull();
        expect(applyScope(canvas, 'primary_activities')).toBeNull();
    });
});

describe('applyStyleToSiblings', () => {
    it('gives every Domain of the Area the style and leaves places and sizes', () => {
        const { apply, graph, canvas, selection, restrictions, editor } = view();
        apply(['d1'], { fill: '#112233', border_width: 2 });
        apply(['d2'], { corner_radius: 5 });
        const before = ['d1', 'd2', 'd3'].map((id) => boxOf(editor, id));
        const result = restrictions.privileged(() => applyStyleToSiblings(selection(['d1']), canvas, 'd1', { measurer: fakeMeasurer }));
        expect(result.ids).toEqual(['d2']);
        expect(storedStyle(graph, 'd2')).toEqual({ fill: '#112233', border_width: 2 });
        expect(storedStyle(graph, 'd3')).toEqual({});
        expect(['d1', 'd2', 'd3'].map((id) => boxOf(editor, id))).toEqual(before);
    });

    it('is undone in one step', () => {
        const { apply, graph, canvas, selection, restrictions, editor } = view();
        apply(['a2'], { fill: '#112233' });
        restrictions.privileged(() => applyStyleToSiblings(selection(['a2']), canvas, 'a2', { measurer: fakeMeasurer }));
        expect(storedStyle(graph, 'a1').fill).toBe('#112233');
        editor.undoAction();
        expect(storedStyle(graph, 'a1')).toEqual({});
        expect(storedStyle(graph, 'a2').fill).toBe('#112233');
    });
});
