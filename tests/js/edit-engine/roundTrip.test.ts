import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import type { CanvasLayoutInput, ElementLayout } from '../../../resources/js/layout/types';
import type { LayoutPayload } from '../../../resources/js/edit-engine/DrawingEngine';
import { toPayload } from '../../../resources/js/edit-engine/payload';
import { buildScene, readPlaces } from '../../../resources/js/edit-engine/scene';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element, withElements } from '../layout/support';

const stored: LayoutPayload = {
    structure_version: 41,
    layout_version: 17,
    format_version: 1,
    areas: {
        primary_activities: { x: 40, y: 400, width: 1100, height: 380, z: 0, style: { fill: '#FFFFFF' } },
    },
    elements: {
        'dom-1': { mode: 'manual', x: 16, y: 48, width: 286, height: 204, z: 2, style: { fill: '#F6E6CC' } },
        'act-1': { mode: 'automatic' },
        'act-2': { mode: 'manual', x: 8, y: 96, width: 200, height: 40, z: 0 },
        'dom-2': { mode: 'automatic' },
        'act-3': { mode: 'automatic' },
    },
};

const elements = [
    element('dom-1', 'primary_activities', 'domain', 'First domain'),
    element('act-1', 'primary_activities', 'activity', 'First activity', 'dom-1', 1),
    element('act-2', 'primary_activities', 'activity', 'Second activity', 'dom-1', 2),
    element('dom-2', 'supporting_activities', 'domain', 'Second domain'),
    element('act-3', 'supporting_activities', 'activity', 'Third activity', 'dom-2', 1),
];

function layouts(): Record<string, ElementLayout> {
    const result: Record<string, ElementLayout> = {};
    for (const [id, area] of Object.entries(stored.areas)) {
        result[id] = { mode: 'manual', x: area.x, y: area.y, width: area.width, height: area.height, z: area.z };
    }
    for (const [id, entry] of Object.entries(stored.elements)) {
        if (entry.mode === 'manual') result[id] = { mode: 'manual', x: entry.x, y: entry.y, width: entry.width, height: entry.height, z: entry.z };
    }
    return result;
}

function drawn() {
    const input: CanvasLayoutInput = { ...withElements(elements), layouts: layouts() };
    return computeCanvasLayout(input, fakeMeasurer);
}

describe('drawing adapter round trip', () => {
    it('gives back the layout it was mounted with, without a difference', () => {
        const canvas = drawn();
        const graph = buildScene(canvas);

        expect(toPayload(stored, canvas, readPlaces(graph, canvas))).toEqual(stored);
    });

    it('never returns a node the input did not hold', () => {
        const canvas = drawn();
        const graph = buildScene(canvas);
        graph.createNodeWithId('stray', 'FRAME', 'dom-1', { name: 'stray', x: 0, y: 0, width: 10, height: 10 });

        const payload = toPayload(stored, canvas, readPlaces(graph, canvas));

        expect(Object.keys(payload.elements).sort()).toEqual(Object.keys(stored.elements).sort());
        expect(Object.keys(payload.areas)).toEqual(['primary_activities']);
    });

    it('holds every Element of the Canvas and no Area without a manual layout', () => {
        const canvas = drawn();
        const graph = buildScene(canvas);

        const payload = toPayload(stored, canvas, readPlaces(graph, canvas));

        expect(payload.elements['act-1']).toEqual({ mode: 'automatic' });
        expect(payload.areas.direction).toBeUndefined();
        expect(payload.areas.supporting_activities).toBeUndefined();
    });

    it('reads a moved block back as manual at its new place, keeping its drawn height out of the stored height', () => {
        const canvas = drawn();
        const graph = buildScene(canvas);
        graph.updateNode('dom-1', { x: 24, y: 80, width: 300 });
        graph.updateNode('act-1', { x: 20, y: 30 });

        const payload = toPayload(stored, canvas, readPlaces(graph, canvas));
        const domain = payload.elements['dom-1'];
        const activity = payload.elements['act-1'];

        expect(domain).toEqual({ mode: 'manual', x: 24, y: 80, width: 300, height: 204, z: 2, style: { fill: '#F6E6CC' } });
        expect(activity.mode).toBe('manual');
        expect(activity).toMatchObject({ x: 20, y: 30, height: Math.round(canvas.blocks.find(({ id }) => id === 'act-1')!.drawnHeight) });
    });
});
