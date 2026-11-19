import type { AreaKey, BlockStyle, CanvasLayoutInput, DrawnBlock, DrawnCanvas, TextMeasurer } from './types';

export const ARTBOARD = { width: 1680, height: 1188, directionHeight: 180 } as const;

const placeholder: BlockStyle = {
    fill: null,
    border: null,
    textColour: '#171B21',
    labelColour: '#171B21',
    font: { family: 'Inter', size: 13, weight: 400, italic: false },
    lineHeight: 18,
    radius: 0,
};

const areaBlock = (key: AreaKey): DrawnBlock => ({
    id: key,
    kind: 'area',
    areaKey: key,
    parentId: null,
    x: 0,
    y: 0,
    width: 0,
    drawnHeight: 0,
    z: 0,
    textLines: [],
    modality: null,
    system: false,
    style: placeholder,
});

export function computeCanvasLayout(input: CanvasLayoutInput, measurer: TextMeasurer): DrawnCanvas {
    void measurer;
    const blocks = [...input.areas].sort((a, b) => a.position - b.position).map((area) => areaBlock(area.key));
    return { blocks, direction: [], warnings: [], beyondArtboardCount: 0 };
}

export type * from './types';
