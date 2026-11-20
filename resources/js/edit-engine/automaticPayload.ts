import type { LayoutPayload } from './DrawingEngine';

export const automaticPayload = (ids: string[]): LayoutPayload => ({
    structure_version: 0,
    layout_version: 0,
    format_version: 1,
    areas: {},
    elements: Object.fromEntries(ids.map((id) => [id, { mode: 'automatic' as const }])),
});
