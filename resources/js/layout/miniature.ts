import { ARTBOARD_HEIGHT, ARTBOARD_WIDTH } from './arrangeAreas';
import type { DrawnCanvas } from './types';

export interface MiniatureRect {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    fill: string;
    fillOpacity: number;
    stroke: string | null;
    strokeWidth: number;
    dashed: boolean;
}

export interface Miniature {
    width: number;
    height: number;
    rects: MiniatureRect[];
}

export function buildMiniature(canvas: DrawnCanvas, width: number): Miniature {
    const scale = width / ARTBOARD_WIDTH;
    const round = (value: number): number => Math.round(value * scale * 100) / 100;

    return {
        width,
        height: round(ARTBOARD_HEIGHT),
        rects: [...canvas.blocks]
            .sort((a, b) => a.z - b.z)
            .filter((block) => (block.kind === 'area' || block.kind === 'domain') && block.style.fill !== null)
            .map((block) => ({
                id: block.id,
                x: round(block.x),
                y: round(block.y),
                width: round(block.width),
                height: round(block.drawnHeight),
                fill: block.style.fill as string,
                fillOpacity: block.style.fillOpacity / 100,
                stroke: block.style.border,
                strokeWidth: Math.max(0.5, round(block.style.borderWidth)),
                dashed: block.style.borderStyle === 'dashed',
            })),
    };
}
