import type { DrawnCanvas } from '../layout';
import type { LayoutPayload, LayoutStyle, SavedArea, SavedElement } from './DrawingEngine';
import { ARTBOARD_HEIGHT, MIN_HEIGHT } from './constraints';
import { placesOf, type Place } from './scene';

const same = (one: Place | undefined, other: Place | undefined) => !!one && !!other && one.x === other.x && one.y === other.y && one.width === other.width && one.height === undefined;

export function toPayload(base: LayoutPayload, canvas: DrawnCanvas, places: Map<string, Place>, orders: Map<string, number> = new Map(), styles: Map<string, LayoutStyle> = new Map()): LayoutPayload {
    const built = placesOf(canvas);
    const areas: Record<string, SavedArea> = {};
    const elements: Record<string, SavedElement> = {};
    const styleOf = (id: string, stored: { style?: LayoutStyle } | null | undefined): { style?: LayoutStyle } => {
        const style = styles.has(id) ? styles.get(id) : stored?.style;
        return style && Object.keys(style).length > 0 ? { style } : {};
    };

    for (const block of canvas.blocks) {
        const place = places.get(block.id);
        if (!place) continue;
        if (block.kind === 'area') {
            const stored = base.areas[block.id];
            if (block.areaKey === 'direction' || (!stored && same(place, built.get(block.id)) && !orders.has(block.id) && !styleOf(block.id, null).style)) continue;
            areas[block.id] = { x: place.x, y: place.y, width: place.width, height: place.height ?? stored?.height ?? Math.min(block.drawnHeight, ARTBOARD_HEIGHT - place.y), z: orders.get(block.id) ?? stored?.z ?? 0, ...styleOf(block.id, stored) };
            continue;
        }
        const geometryChanged = !same(place, built.get(block.id));
        const entry = base.elements[block.id];
        const stored = entry?.mode === 'manual' ? entry : null;
        if (!stored && !geometryChanged && !orders.has(block.id) && !styleOf(block.id, null).style) {
            elements[block.id] = { mode: 'automatic' };
            continue;
        }
        elements[block.id] = { mode: 'manual', x: place.x, y: place.y, width: place.width, height: place.height ?? stored?.height ?? (geometryChanged ? Math.max(MIN_HEIGHT, Math.round(block.drawnHeight)) : MIN_HEIGHT), z: orders.get(block.id) ?? stored?.z ?? 0, ...styleOf(block.id, stored) };
    }

    return { structure_version: base.structure_version, layout_version: base.layout_version, format_version: base.format_version, areas, elements };
}
