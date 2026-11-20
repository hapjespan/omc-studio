import { onBeforeUnmount, reactive } from 'vue';

export const shellLayout = reactive({ widePadding: false, quietStatus: false, headerSlots: 0 });

function option(key: 'widePadding' | 'quietStatus'): void {
    shellLayout[key] = true;
    onBeforeUnmount(() => (shellLayout[key] = false));
}

export const useWideMain = (): void => option('widePadding');

export function useHeaderSlot(): void {
    shellLayout.headerSlots++;
    onBeforeUnmount(() => shellLayout.headerSlots--);
}

export const useQuietStatus = (): void => option('quietStatus');
