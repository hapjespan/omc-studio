import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, type Ref } from 'vue';

export function useDeferredLoad(only: string[], pending: () => boolean): { failed: Ref<boolean>; retry: () => void } {
    const failed = ref(false);
    const fail = (): void => {
        if (pending()) failed.value = true;
    };
    const stops = [router.on('httpException', fail), router.on('networkError', fail)];
    onBeforeUnmount(() => stops.forEach((stop) => stop()));

    return {
        failed,
        retry: (): void => {
            failed.value = false;
            router.reload({ only });
        },
    };
}
