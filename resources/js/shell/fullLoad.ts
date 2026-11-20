import { router } from '@inertiajs/vue3';

const isDrawing = (path: string): boolean => /^\/canvases\/[^/]+\/drawing$/.test(path);

export const leaveGuards = new Set<(destination: string) => boolean>();

export function loadDrawingWithFullPages(): void {
    router.on('before', (event) => {
        const target = new URL(event.detail.visit.url);
        if (target.pathname === window.location.pathname) return;
        if ([...leaveGuards].some((guard) => guard(target.href))) {
            event.preventDefault();
            return;
        }
        if (!isDrawing(target.pathname) && !isDrawing(window.location.pathname)) return;
        event.preventDefault();
        window.location.assign(target.href);
    });
}
