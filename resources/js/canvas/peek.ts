import { ref } from 'vue';

export const CLOSE_DELAY = 200;

export type PeekSource = 'pointer' | 'focus';

export function createPeek(delay = CLOSE_DELAY) {
    const peeked = ref<string | null>(null);
    let focused: string | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const peek = (id: string | null, source: PeekSource = 'pointer'): void => {
        clearTimeout(timer);
        if (source === 'focus') focused = id;
        if (id) peeked.value = id;
        else if (source === 'pointer' && focused) peeked.value = focused;
        else timer = setTimeout(() => (peeked.value = null), delay);
    };
    const dismiss = (): void => {
        clearTimeout(timer);
        focused = null;
        peeked.value = null;
    };

    return { peeked, peek, dismiss };
}
