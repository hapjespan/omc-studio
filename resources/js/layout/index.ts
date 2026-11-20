import { areaChrome, AREA_HEADER, AREA_LETTER_SPACING, AREA_PADDING, arrangeAreas, areaWidth, ARTBOARD_HEIGHT, ARTBOARD_WIDTH } from './arrangeAreas';
import { DIRECTION_HEIGHT, layoutDirection } from './direction';
import { flowDomains, type Flow, type FlowContext } from './flowDomains';
import { flowList } from './listAreas';
import { resolveStyle } from './resolveStyle';
import { manualRect, stackOrder } from './manual';
import { buildDomains, type TreeNode } from './tree';
import { computeWarnings } from './warnings';
import { wrapText } from './wrapText';
import type { AreaKey, CanvasLayoutInput, DrawnBlock, DrawnCanvas, LayoutWarning, TextMeasurer } from './types';

export const ARTBOARD = { width: ARTBOARD_WIDTH, height: ARTBOARD_HEIGHT, directionHeight: DIRECTION_HEIGHT } as const;

export function computeCanvasLayout(input: CanvasLayoutInput, measurer: TextMeasurer): DrawnCanvas {
    const rect = (id: string) => manualRect(input.layouts?.[id]);
    const context: FlowContext = { measurer, rect, style: (element, level) => resolveStyle(element.area, level, input.layouts?.[element.id]?.style) };
    const areas = [...input.areas].sort((a, b) => a.position - b.position);
    const flows = new Map<AreaKey, Flow>();
    const domainsByArea = new Map<AreaKey, TreeNode[]>();
    const required: Partial<Record<AreaKey, number>> = {};

    for (const area of areas.filter((candidate) => candidate.key !== 'direction')) {
        const domains = buildDomains(input.elements, area.key);
        domainsByArea.set(area.key, domains);
        const flow = (area.kind === 'list' ? flowList : flowDomains)(domains, area.key, (rect(area.key)?.width ?? areaWidth(area.key)) - 2 * AREA_PADDING, context);
        flows.set(area.key, flow);
        required[area.key] = areaChrome() + flow.height;
    }

    const frames = arrangeAreas(Object.fromEntries(Object.entries(required).filter(([key]) => !rect(key))));
    for (const area of areas) {
        const own = rect(area.key);
        if (own) frames[area.key] = { x: own.x, y: own.y, width: own.width, height: Math.max(own.height, required[area.key] ?? 0) };
    }
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
            z: stackOrder(0, rect(area.key)),
            textLines: area.key === 'direction' ? [] : wrapText(area.label.toUpperCase(), frame.width - 2 * AREA_PADDING - area.label.length * AREA_LETTER_SPACING * style.font.size, style.font, measurer),
            modality: null,
            system: false,
            style,
        });
        for (const block of flows.get(area.key)?.blocks ?? []) {
            blocks.push({ ...block, x: block.x + frame.x + AREA_PADDING, y: block.y + frame.y + AREA_HEADER + AREA_PADDING });
        }
    }

    const direction = layoutDirection(input.statements, input.directionLabels, measurer);
    const cut: LayoutWarning[] = direction.filter((column) => column.truncated).map((column) => ({ kind: 'directionTextCut', column: column.key }));
    const { warnings, beyondArtboardCount } = computeWarnings(domainsByArea, blocks, new Map([...input.areas.map((area): [string, string] => [area.key, area.label]), ...input.elements.map((element): [string, string] => [element.id, element.name])]));

    return { blocks, direction, warnings: [...cut, ...warnings], beyondArtboardCount };
}

export type * from './types';
