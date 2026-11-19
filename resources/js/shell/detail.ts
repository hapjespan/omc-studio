import { nextTick, reactive } from 'vue';

export const detail = reactive({ open: false, title: '', dirty: false, confirming: false, opener: null as HTMLElement | null });

export function openDetail(title: string, opener: HTMLElement | null = document.activeElement as HTMLElement | null): void {
    detail.title = title;
    detail.opener = opener;
    detail.confirming = false;
    detail.open = true;
}

function close(): void {
    const opener = detail.opener;
    detail.open = false;
    detail.dirty = false;
    detail.confirming = false;
    detail.opener = null;
    void nextTick(() => opener?.focus());
}

export function requestCloseDetail(): void {
    if (detail.dirty) detail.confirming = true;
    else close();
}

export function discardDetail(): void {
    close();
}
