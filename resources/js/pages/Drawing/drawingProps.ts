import type { CanvasLayoutInput } from '../../layout';

export interface DrawingProps {
    canvas: { id: number; name: string; archived: boolean };
    layouts: NonNullable<CanvasLayoutInput['layouts']>;
    versions: { structure: number; layout: number };
    newElements: string[];
    layout: Pick<CanvasLayoutInput, 'areas' | 'directionLabels'>;
    elements: CanvasLayoutInput['elements'];
    statements: CanvasLayoutInput['statements'];
}
