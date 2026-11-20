import { describe, expect, it } from 'vitest';
import type { LayoutPayload } from '../../../resources/js/edit-engine/DrawingEngine';
import { setStyle, storedStyle } from '../../../resources/js/edit-engine/commands/style';
import { toPayload } from '../../../resources/js/edit-engine/payload';
import { readPlaces } from '../../../resources/js/edit-engine/scene';
import { boxOf } from '../../../resources/js/edit-engine/transform';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element } from '../layout/support';
import { manual, open } from './support';

const tree = [
    element('d', 'primary_activities', 'domain', 'Domein'),
    element('a', 'primary_activities', 'activity', 'Activiteit', 'd', 1),
    element('auto', 'supporting_activities', 'domain', 'Automatisch'),
];
const layouts = { primary_activities: manual(0, 900, 900, 250, 0), d: manual(16, 48, 600, 100), a: manual(10, 250, 200, 40) };

const base: LayoutPayload = {
    structure_version: 1,
    layout_version: 1,
    format_version: 1,
    areas: { primary_activities: { x: 0, y: 900, width: 900, height: 250, z: 0 } },
    elements: { d: { mode: 'manual', x: 16, y: 48, width: 600, height: 100, z: 2 }, a: { mode: 'manual', x: 10, y: 250, width: 200, height: 40, z: 2 }, auto: { mode: 'automatic' } },
};

const session = () => {
    const view = open(tree, layouts);
    const apply = (ids: string[], patch: Parameters<typeof setStyle>[2]) => view.restrictions.privileged(() => setStyle(view.selection(ids), view.canvas, patch, { measurer: fakeMeasurer }));
    const payload = () => toPayload(base, view.canvas, readPlaces(view.graph, view.canvas), new Map(), new Map(view.canvas.blocks.map((block) => [block.id, storedStyle(view.graph, block.id)])));
    return { ...view, apply, payload };
};

describe('style and the stored Layout', () => {
    it('keeps the stored height of a grown Domain when its fill changes', () => {
        const { apply, payload, canvas } = session();
        expect(canvas.blocks.find((block) => block.id === 'd')!.drawnHeight).toBeGreaterThan(200);
        apply(['d'], { fill: '#112233' });
        expect(payload().elements.d).toMatchObject({ mode: 'manual', height: 100, style: { fill: '#112233' } });
    });

    it('never writes a place that passes the Artboard for an Area whose content grows', () => {
        const { apply, payload, canvas } = session();
        expect(canvas.blocks.find((block) => block.id === 'primary_activities')!.drawnHeight).toBeGreaterThan(288);
        apply(['primary_activities'], { fill: '#112233' });
        const area = payload().areas.primary_activities;
        expect(area.y + area.height).toBeLessThanOrEqual(1188);
        expect(area.height).toBe(250);
    });

    it('stores the style of an Area that had no stored place, inside the Artboard', () => {
        const { apply, payload } = session();
        apply(['actors'], { fill: '#112233' });
        const area = payload().areas.actors;
        expect(area.style).toEqual({ fill: '#112233' });
        expect(area.y + area.height).toBeLessThanOrEqual(1188);
    });

    it('turns an automatic Element manual with the minimum height and returns it to automatic on undo', () => {
        const { apply, payload, editor } = session();
        apply(['auto'], { fill: '#112233' });
        expect(payload().elements.auto).toMatchObject({ mode: 'manual', height: 24, style: { fill: '#112233' } });
        editor.undoAction();
        expect(payload().elements.auto).toEqual({ mode: 'automatic' });
    });
});

describe('growing for text', () => {
    it('grows the drawn frame to hold a larger title and never stores that height', () => {
        const { apply, payload, editor } = session();
        const before = boxOf(editor, 'a')!;
        apply(['a'], { font: 'inter', font_size: 40 });
        const after = boxOf(editor, 'a')!;
        expect(after.height).toBeGreaterThanOrEqual(40 * 1.4);
        expect(after.height).toBeGreaterThan(before.height);
        expect(payload().elements.a).toMatchObject({ height: 40 });
    });
});
