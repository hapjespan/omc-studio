import type { DrawingEngine } from '../../edit-engine';

export type NavigateAction = 'in' | 'out' | 'actual' | 'left' | 'right' | 'up' | 'down';

const ZOOM_STEP = 1.25;
const PAN_STEP = 120;

export function navigate(engine: DrawingEngine, action: NavigateAction): void {
    if (action === 'in') engine.zoomTo(engine.getZoom() * ZOOM_STEP);
    else if (action === 'out') engine.zoomTo(engine.getZoom() / ZOOM_STEP);
    else if (action === 'actual') engine.zoomTo100();
    else if (action === 'left') engine.panBy(PAN_STEP, 0);
    else if (action === 'right') engine.panBy(-PAN_STEP, 0);
    else if (action === 'up') engine.panBy(0, PAN_STEP);
    else engine.panBy(0, -PAN_STEP);
}
