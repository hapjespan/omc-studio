import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, type ComputedRef } from 'vue';
import { routes } from '../../routes';
import type { UndoState } from './ListView.vue';

export type FlashUndo = { token: string; message: string; scope?: string };

export type UndoFlash = { state: ComputedRef<UndoState | null>; start: () => void; run: () => void; dismiss: () => void };

export function useUndoFlash(scope = ''): UndoFlash {
    const page = usePage();
    const dismissed = ref('');
    const refusal = ref<string | null>(null);
    const flash = computed(() => {
        const undo = (page.props.flash as { undo?: FlashUndo | null } | undefined)?.undo ?? null;
        return undo && (undo.scope ?? '') === scope ? undo : null;
    });

    return {
        state: computed(() => (flash.value && flash.value.token !== dismissed.value ? { id: flash.value.token, message: flash.value.message, refusal: refusal.value } : null)),
        start: () => (refusal.value = null),
        run: () => {
            const token = flash.value?.token;
            if (!token) return;
            router.post(routes.undo(token), {}, { preserveScroll: true, preserveState: true, onSuccess: () => (dismissed.value = token), onError: (failed) => (refusal.value = failed.rule ?? Object.values(failed)[0] ?? null) });
        },
        dismiss: () => {
            dismissed.value = flash.value?.token ?? '';
            refusal.value = null;
        },
    };
}
