import { areaChrome, AREA_HEADER, AREA_PADDING, arrangeAreas, areaWidth, ARTBOARD_HEIGHT, ARTBOARD_WIDTH } from './arrangeAreas';
import { DIRECTION_HEIGHT, layoutDirection } from './direction';
import { flowDomains, type Flow, type FlowContext } from './flowDomains';
import { flowList } from './listAreas';
import { resolveStyle } from './resolveStyle';
import { buildDomains } from './tree';
import { wrapText } from './wrapText';
import type { AreaKey, CanvasLayoutInput, DrawnBlock, DrawnCanvas, LayoutWarning, TextMeasurer } from './types';

export const ARTBOARD = { width: ARTBOARD_WIDTH, height: ARTBOARD_HEIGHT, directionHeight: DIRECTION_HEIGHT } as const;

export function computeCanvasLayout(input: CanvasLayoutInput, measurer: TextMeasurer): DrawnCanvas {
    const context: FlowContext = { measurer, style: (element, level) => resolveStyle(element.area, level, input.layouts?.[element.id]?.style) };
    const areas = [...input.areas].sort((a, b) => a.position - b.position);
    const flows = new Map<AreaKey, Flow>();
    const required: Partial<Record<AreaKey, number>> = {};

    for (const area of areas.filter((candidate) => candidate.key !== 'direction')) {
        const flow: Flow =
            (area.kind === 'list' ? flowList : flowDomains)(buildDomains(input.elements, area.key), area.key, areaWidth(area.key) - 2 * AREA_PADDING, context);
        flows.set(area.key, flow);
        required[area.key] = areaChrome() + flow.height;
    }

    const frames = arrangeAreas(required);
    const blocks: DrawnBlock[] = [];
    for (const area of areas) {
        const frame = frames[area.key];
        const style = resolveStyle(area.key, 'area');
        blocks.push({
            id: area.key,
            kind: 'area',
            areaKey: area.key,
            parentId: null,
            x: frame.x,
            y: frame.y,
            width: frame.width,
            drawnHeight: frame.height,
            z: 0,
            textLines: area.key === 'direction' ? [] : wrapText(area.label.toUpperCase(), frame.width - 2 * AREA_PADDING, style.font, measurer),
            modality: null,
            system: false,
            style,
        });
        for (const block of flows.get(area.key)?.blocks ?? []) {
            blocks.push({ ...block, x: block.x + frame.x + AREA_PADDING, y: block.y + frame.y + AREA_HEADER + AREA_PADDING });
        }
    }

    const direction = layoutDirection(input.statements, input.directionLabels, measurer);
    const warnings: LayoutWarning[] = direction.filter((column) => column.truncated).map((column) => ({ kind: 'directionTextCut', column: column.key }));

    return { blocks, direction, warnings, beyondArtboardCount: 0 };
}

export type * from './types';
