import { router } from '@inertiajs/vue3';

export function overlayQuery(search: string, changes: Record<string, string | null>): Record<string, string> {
    const params = new URLSearchParams(search);
    params.delete('element');
    for (const [key, value] of Object.entries(changes)) {
        if (value === null || value === '') params.delete(key);
        else params.set(key, value);
    }
    return Object.fromEntries(params);
}

export function visitOverlay(changes: Record<string, string | null>): void {
    router.get(window.location.pathname, overlayQuery(window.location.search, changes), { preserveState: true, preserveScroll: true, replace: true });
}
