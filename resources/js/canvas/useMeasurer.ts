import { onMounted, shallowRef, type ShallowRef } from 'vue';
import { createBrowserMeasurer } from '../layout/browserMeasurer';
import type { TextMeasurer } from '../layout/types';

export function useMeasurer(): ShallowRef<TextMeasurer | null> {
    const measurer = shallowRef<TextMeasurer | null>(null);
    onMounted(async () => (measurer.value = await createBrowserMeasurer().catch(() => null)));

    return measurer;
}
