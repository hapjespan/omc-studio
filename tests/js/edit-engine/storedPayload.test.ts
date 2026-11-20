import { describe, expect, it } from 'vitest';
import { automaticPayload, storedPayload } from '../../../resources/js/edit-engine/automaticPayload';

describe('storedPayload', () => {
    const layouts = {
        primary_activities: { mode: 'manual' as const, x: 0, y: 200, width: 900, height: 300, z: 0, style: {} },
        'dom-1': { mode: 'manual' as const, x: 16, y: 48, width: 286, height: 204, z: 2, style: { fill: '#F6E6CC' } },
    };
    const payload = storedPayload(layouts, ['dom-1', 'act-1'], { structure: 7, layout: 3 });

    it('carries the real versions', () => {
        expect([payload.structure_version, payload.layout_version, payload.format_version]).toEqual([7, 3, 1]);
    });

    it('holds a manual Area, a manual Element with its style, and automatic for the rest', () => {
        expect(payload.areas).toEqual({ primary_activities: { x: 0, y: 200, width: 900, height: 300, z: 0 } });
        expect(payload.elements).toEqual({
            'dom-1': { mode: 'manual', x: 16, y: 48, width: 286, height: 204, z: 2, style: { fill: '#F6E6CC' } },
            'act-1': { mode: 'automatic' },
        });
    });

    it('is all automatic without stored layouts', () => {
        expect(storedPayload({}, ['a'], { structure: 1, layout: 0 }).elements).toEqual(automaticPayload(['a']).elements);
        expect(storedPayload({}, ['a'], { structure: 1, layout: 0 }).areas).toEqual({});
    });
});

describe('mergeLayout', () => {
    it('keeps own changes for Elements that still exist, takes the rest and the versions from the fresh payload', async () => {
        const { mergeLayout } = await import('../../../resources/js/edit-engine/automaticPayload');
        const own = storedPayload({ 'dom-1': { mode: 'manual', x: 99, y: 48, width: 286, height: 204, z: 2 }, gone: { mode: 'manual', x: 1, y: 1, width: 50, height: 50, z: 0 } }, ['dom-1', 'gone'], { structure: 1, layout: 1 });
        const fresh = storedPayload({}, ['dom-1', 'added'], { structure: 2, layout: 5 });
        const merged = mergeLayout(own, fresh);
        expect(merged.structure_version).toBe(2);
        expect(merged.layout_version).toBe(5);
        expect(Object.keys(merged.elements).sort()).toEqual(['added', 'dom-1']);
        expect(merged.elements['dom-1']).toMatchObject({ mode: 'manual', x: 99 });
        expect(merged.elements.added).toEqual({ mode: 'automatic' });
    });
});

describe('payloadLayouts', () => {
    it('turns the manual entries of a payload back into layouts for the engine input', async () => {
        const { payloadLayouts } = await import('../../../resources/js/edit-engine/automaticPayload');
        const payload = storedPayload({ primary_activities: { mode: 'manual', x: 0, y: 200, width: 900, height: 300, z: 0 }, 'dom-1': { mode: 'manual', x: 16, y: 48, width: 286, height: 204, z: 2, style: { fill: '#F6E6CC' } } }, ['dom-1', 'act-1'], { structure: 1, layout: 1 });
        expect(payloadLayouts(payload)).toEqual({
            primary_activities: { mode: 'manual', x: 0, y: 200, width: 900, height: 300, z: 0 },
            'dom-1': { mode: 'manual', x: 16, y: 48, width: 286, height: 204, z: 2, style: { fill: '#F6E6CC' } },
        });
    });
});
