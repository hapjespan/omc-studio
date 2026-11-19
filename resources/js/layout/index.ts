import { arrangeAreas, ARTBOARD_HEIGHT, ARTBOARD_WIDTH, type Frame } from './arrangeAreas';
import { DIRECTION_HEIGHT, layoutDirection } from './direction';
import type { AreaKey, BlockStyle, CanvasLayoutInput, DrawnBlock, DrawnCanvas, LayoutWarning, TextMeasurer } from './types';

export const ARTBOARD = { width: ARTBOARD_WIDTH, height: ARTBOARD_HEIGHT, directionHeight: DIRECTION_HEIGHT } as const;

const placeholder: BlockStyle = {
    fill: null,
    border: null,
    textColour: '#171B21',
    labelColour: '#171B21',
    font: { family: 'Inter', size: 13, weight: 400, italic: false },
    lineHeight: 18,
    radius: 0,
};

const areaBlock = (key: AreaKey, frame: Frame): DrawnBlock => ({
    id: key,
    kind: 'area',
    areaKey: key,
    parentId: null,
    x: frame.x,
    y: frame.y,
    width: frame.width,
    drawnHeight: frame.height,
    z: 0,
    textLines: [],
    modality: null,
    system: false,
    style: placeholder,
});

export function computeCanvasLayout(input: CanvasLayoutInput, measurer: TextMeasurer): DrawnCanvas {
    const frames = arrangeAreas({});
    const blocks = [...input.areas].sort((a, b) => a.position - b.position).map((area) => areaBlock(area.key, frames[area.key]));
    const direction = layoutDirection(input.statements, input.directionLabels, measurer);
    const warnings: LayoutWarning[] = direction.filter((column) => column.truncated).map((column) => ({ kind: 'directionTextCut', column: column.key }));
    return { blocks, direction, warnings, beyondArtboardCount: 0 };
}

export type * from './types';
