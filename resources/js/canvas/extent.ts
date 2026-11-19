import { ARTBOARD } from '../layout';
import type { DrawnCanvas } from '../layout/types';

export const contentHeight = (drawn: DrawnCanvas): number => Math.max(ARTBOARD.height, ...drawn.blocks.map((block) => block.y + block.drawnHeight));
