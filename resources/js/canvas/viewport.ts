import { onBeforeUnmount } from 'vue';

interface CanvasViewport {
    x: number;
    y: number;
    zoom: number;
}

let restored: CanvasViewport | undefined;
let current: (() => CanvasViewport | undefined) | undefined;

export function useViewportSource(read: () => CanvasViewport | undefined): void {
    current = read;
    onBeforeUnmount(() => { if (current === read) current = undefined; });
}

export function modeDestination(url: string): string {
    const source = /^\/canvases\/([^/]+)\/(drawing|view)$/.exec(window.location.pathname);
    if (!source || !current) return url;
    const destination = new URL(url, window.location.href);
    if (destination.pathname !== `/canvases/${source[1]}/${source[2] === 'drawing' ? 'view' : 'drawing'}`) return url;
    const viewport = current();
    if (viewport) destination.hash = `viewport=${viewport.x},${viewport.y},${viewport.zoom}`;
    return destination.href;
}

export function readModeDestination(): void {
    const url = new URL(window.location.href);
    if (!/^\/canvases\/[^/]+\/(drawing|view)$/.test(url.pathname) || !url.hash.startsWith('#viewport=')) return;
    const values = url.hash.slice('#viewport='.length).split(',').map(Number);
    url.hash = '';
    history.replaceState(history.state, '', url);
    const [x, y, zoom] = values;
    if (values.length === 3 && values.every(Number.isFinite) && zoom >= 0.02 && zoom <= 256) restored = { x, y, zoom };
}

export function takeViewport(): CanvasViewport | undefined {
    const position = restored;
    restored = undefined;
    return position;
}
