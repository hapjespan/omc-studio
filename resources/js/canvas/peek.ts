import { ref } from 'vue';

export const CLOSE_DELAY = 200;

export function createPeek(delay = CLOSE_DELAY) {
    const peeked = ref<string | null>(null);
    let timer: ReturnType<typeof setTimeout> | undefined;

    const peek = (id: string | null): void => {
        clearTimeout(timer);
        if (id) peeked.value = id;
        else timer = setTimeout(() => (peeked.value = null), delay);
    };
    const dismiss = (): void => {
        clearTimeout(timer);
        peeked.value = null;
    };

    return { peeked, peek, dismiss };
}
