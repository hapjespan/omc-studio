import { AREA_PADDING, areaWidth, isListArea } from './arrangeAreas';
import { CARD_GAP, CLUSTER_INDENT, columnCount } from './flowDomains';
import { elementPadding as padding } from './padding';
import { ITEM_INDENT } from './listAreas';
import type { AreaKey, LayoutElement } from './types';

type WidthElement = Pick<LayoutElement, 'id' | 'area' | 'parent' | 'kind'>;

export interface WidthOverrides {
    areas?: Partial<Record<AreaKey, number>>;
    elements?: Record<string, number>;
}

export function automaticWidths(elements: WidthElement[], overrides: WidthOverrides = {}): Record<string, number> {
    const byId = new Map(elements.map((element) => [element.id, element]));
    const widths: Record<string, number> = {};

    const widthOf = (element: WidthElement): number => {
        if (element.id in widths) return widths[element.id];
        const stored = overrides.elements?.[element.id];
        let width: number;
        if (stored !== undefined) {
            width = stored;
        } else if (element.parent === null) {
            const inner = (overrides.areas?.[element.area] ?? areaWidth(element.area)) - 2 * AREA_PADDING;
            const columns = isListArea(element.area) ? 1 : columnCount(inner);
            width = Math.floor((inner - (columns - 1) * CARD_GAP) / columns);
        } else {
            const parent = byId.get(element.parent) as WidthElement;
            const parentWidth = widthOf(parent);
            width = isListArea(element.area)
                ? parentWidth - ITEM_INDENT
                : parentWidth - 2 * padding[parent.kind].x - (element.kind === 'cluster' ? CLUSTER_INDENT : 0);
        }
        widths[element.id] = width;
        return width;
    };

    elements.forEach(widthOf);
    return widths;
}
