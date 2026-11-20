import { onBeforeUnmount, reactive } from 'vue';

export const shellLayout = reactive({ widePadding: false, headerSlots: 0 });

export function useWideMain(): void {
    shellLayout.widePadding = true;
    onBeforeUnmount(() => (shellLayout.widePadding = false));
}
