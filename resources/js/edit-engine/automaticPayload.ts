import type { ElementLayout } from '../layout';
import type { LayoutPayload, LayoutStyle } from './DrawingEngine';

export const automaticPayload = (ids: string[]): LayoutPayload => ({
    structure_version: 0,
    layout_version: 0,
    format_version: 1,
    areas: {},
    elements: Object.fromEntries(ids.map((id) => [id, { mode: 'automatic' as const }])),
});

export interface StoredVersions {
    structure: number;
    layout: number;
}

export function storedPayload(layouts: Record<string, ElementLayout>, ids: string[], versions: StoredVersions): LayoutPayload {
    const known = new Set(ids);
    const box = (layout: ElementLayout) => ({ x: layout.x ?? 0, y: layout.y ?? 0, width: layout.width ?? 0, height: layout.height ?? 0, z: layout.z ?? 0 });
    const style = (layout: ElementLayout) => (layout.style && Object.keys(layout.style).length > 0 ? { style: layout.style as LayoutStyle } : {});

    return {
        structure_version: versions.structure,
        layout_version: versions.layout,
        format_version: 1,
        areas: Object.fromEntries(Object.entries(layouts).filter(([key, layout]) => !known.has(key) && layout.mode === 'manual').map(([key, layout]) => [key, { ...box(layout), ...style(layout) }])),
        elements: Object.fromEntries(ids.map((id) => [id, layouts[id]?.mode === 'manual' ? { mode: 'manual' as const, ...box(layouts[id]), ...style(layouts[id]) } : { mode: 'automatic' as const }])),
    };
}
