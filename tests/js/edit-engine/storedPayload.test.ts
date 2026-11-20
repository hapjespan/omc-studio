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
