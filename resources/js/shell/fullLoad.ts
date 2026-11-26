import { router } from '@inertiajs/vue3';
import type { RequestPayload } from '@inertiajs/core';

const isDrawing = (path: string): boolean => /^\/canvases\/[^/]+\/drawing$/.test(path);

export interface LeaveVisit {
    url: string;
    method: string;
    data: RequestPayload;
}

export const leaveGuards = new Set<(visit: LeaveVisit) => boolean>();

export function proceed(visit: LeaveVisit): void {
    if (visit.method === 'get') window.location.assign(visit.url);
    else void router.visit(visit.url, { method: visit.method as 'post', data: visit.data });
}

export function loadDrawingWithFullPages(): void {
    router.on('before', (event) => {
        const { visit } = event.detail;
        const target = new URL(visit.url);
        if (visit.method === 'get' && target.pathname === window.location.pathname) return;
        if ([...leaveGuards].some((guard) => guard({ url: target.href, method: visit.method, data: visit.data }))) {
            event.preventDefault();
            return;
        }
        if (visit.method !== 'get') return;
        if (!isDrawing(target.pathname) && !isDrawing(window.location.pathname)) return;
        event.preventDefault();
        window.location.assign(target.href);
    });
}
