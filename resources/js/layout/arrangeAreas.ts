import { DIRECTION_HEIGHT } from './direction';
import type { AreaKey } from './types';

export interface Frame {
    x: number;
    y: number;
    width: number;
    height: number;
}

export const ARTBOARD_WIDTH = 1680;
export const ARTBOARD_HEIGHT = 1188;
export const AREA_GAP = 12;
export const AREA_PADDING = 12;
export const AREA_HEADER = 28;
const LIST_COLUMN = 240;
const MIN_TOP_ROW = 160;

const rightX = AREA_GAP + 3 * LIST_COLUMN + 3 * AREA_GAP;
const topWidth = (ARTBOARD_WIDTH - 3 * AREA_GAP) / 2;

const listColumns: AreaKey[] = ['actors', 'channels', 'value_propositions'];

export const isListArea = (key: AreaKey): boolean => listColumns.includes(key);

export const areaWidth = (key: AreaKey): number => {
    if (key === 'direction') return ARTBOARD_WIDTH;
    if (key === 'steering' || key === 'governing') return topWidth;
    if (isListArea(key)) return LIST_COLUMN;
    return ARTBOARD_WIDTH - AREA_GAP - rightX;
};

export const areaChrome = (): number => AREA_HEADER + 2 * AREA_PADDING;

export function arrangeAreas(required: Partial<Record<AreaKey, number>>): Record<AreaKey, Frame> {
    const need = (key: AreaKey): number => required[key] ?? 0;
    const topY = DIRECTION_HEIGHT + AREA_GAP;
    const topHeight = Math.max(MIN_TOP_ROW, need('steering'), need('governing'));
    const lowerY = topY + topHeight + AREA_GAP;
    const available = ARTBOARD_HEIGHT - AREA_GAP - lowerY;
    const primaryHeight = Math.max(Math.floor((available - AREA_GAP) / 2), need('primary_activities'));
    const supportingHeight = Math.max(available - AREA_GAP - primaryHeight, need('supporting_activities'));
    const columnHeight = Math.max(primaryHeight + AREA_GAP + supportingHeight, ...listColumns.map(need));

    const frames = {
        direction: { x: 0, y: 0, width: ARTBOARD_WIDTH, height: DIRECTION_HEIGHT },
        steering: { x: AREA_GAP, y: topY, width: topWidth, height: topHeight },
        governing: { x: AREA_GAP + topWidth + AREA_GAP, y: topY, width: topWidth, height: topHeight },
        primary_activities: { x: rightX, y: lowerY, width: areaWidth('primary_activities'), height: primaryHeight },
        supporting_activities: { x: rightX, y: lowerY + primaryHeight + AREA_GAP, width: areaWidth('supporting_activities'), height: supportingHeight },
    } as Record<AreaKey, Frame>;
    listColumns.forEach((key, index) => {
        frames[key] = { x: AREA_GAP + index * (LIST_COLUMN + AREA_GAP), y: lowerY, width: LIST_COLUMN, height: columnHeight };
    });

    return frames;
}
