import { router } from '@inertiajs/vue3';

const isDrawing = (path: string): boolean => /^\/canvases\/[^/]+\/drawing$/.test(path);

export function loadDrawingWithFullPages(): void {
    router.on('before', (event) => {
        const target = new URL(event.detail.visit.url);
        if (!isDrawing(target.pathname) && !isDrawing(window.location.pathname)) return;
        event.preventDefault();
        window.location.assign(target.href);
    });
}
