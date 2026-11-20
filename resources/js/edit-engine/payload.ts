import type { DrawnCanvas } from '../layout';
import type { LayoutPayload, SavedArea, SavedElement } from './DrawingEngine';
import { placesOf, type Place } from './scene';

const same = (one: Place | undefined, other: Place | undefined) => !!one && !!other && one.x === other.x && one.y === other.y && one.width === other.width && one.height === undefined;

export function toPayload(base: LayoutPayload, canvas: DrawnCanvas, places: Map<string, Place>, orders: Map<string, number> = new Map()): LayoutPayload {
    const built = placesOf(canvas);
    const areas: Record<string, SavedArea> = {};
    const elements: Record<string, SavedElement> = {};

    for (const block of canvas.blocks) {
        const place = places.get(block.id);
        if (!place) continue;
        if (block.kind === 'area') {
            const stored = base.areas[block.id];
            if (block.areaKey === 'direction' || (!stored && same(place, built.get(block.id)) && !orders.has(block.id))) continue;
            areas[block.id] = { x: place.x, y: place.y, width: place.width, height: place.height ?? stored?.height ?? block.drawnHeight, z: orders.get(block.id) ?? stored?.z ?? 0, ...(stored?.style ? { style: stored.style } : {}) };
            continue;
        }
        const entry = base.elements[block.id];
        const stored = entry?.mode === 'manual' ? entry : null;
        if (!stored && same(place, built.get(block.id)) && !orders.has(block.id)) {
            elements[block.id] = { mode: 'automatic' };
            continue;
        }
        elements[block.id] = { mode: 'manual', x: place.x, y: place.y, width: place.width, height: place.height ?? stored?.height ?? block.drawnHeight, z: orders.get(block.id) ?? stored?.z ?? 0, ...(stored?.style ? { style: stored.style } : {}) };
    }

    return { structure_version: base.structure_version, layout_version: base.layout_version, format_version: base.format_version, areas, elements };
}
