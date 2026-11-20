import { OpenPencilEngine } from './openPencilEngine';
import type { DrawingEngine } from './DrawingEngine';

export const createDrawingEngine = (): DrawingEngine => new OpenPencilEngine();
export type * from './DrawingEngine';
