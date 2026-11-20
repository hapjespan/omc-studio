import { nextTick, reactive } from 'vue';

export const detail = reactive({ open: false, title: '', kicker: '', dirty: false, confirming: false, opener: null as HTMLElement | null, restore: null as HTMLElement | null });

export function openDetail(title: string, opener: HTMLElement | null = document.activeElement as HTMLElement | null, kicker = ''): void {
    detail.title = title;
    detail.kicker = kicker;
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
    if (!detail.dirty) return close();
    detail.restore = document.activeElement as HTMLElement | null;
    detail.confirming = true;
}

export function discardDetail(): void {
    close();
}

export function dismissDiscard(): void {
    const target = detail.restore ?? document.getElementById('detail-title');
    detail.confirming = false;
    detail.restore = null;
    void nextTick(() => target?.focus());
}
