<script setup lang="ts">
import { nextTick, watch } from 'vue';
import Dialog from '../../components/overlays/Dialog.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';

const props = defineProps<{ open: boolean; busy: boolean; target: string; restore: () => HTMLElement | null }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();

let returnTo: HTMLElement | null = null;
let waiting = false;
const giveFocusBack = (): void => {
    if (!waiting || props.busy) return;
    waiting = false;
    setTimeout(() => returnTo?.focus(), 50);
};
watch(
    () => props.open,
    async (open, wasOpen) => {
        if (open) {
            returnTo = props.restore();
            return;
        }
        if (!wasOpen) return;
        await nextTick();
        waiting = true;
        giveFocusBack();
    },
);
watch(() => props.busy, giveFocusBack);
</script>

<template>
    <Dialog :open="open" :title="t('drawing.move_title', { target })" @update:open="(next) => !next && emit('cancel')">
        <div class="flex flex-wrap gap-2">
            <AppButton variant="primary" @click="emit('confirm')">{{ t('drawing.move_confirm') }}</AppButton>
            <AppButton @click="emit('cancel')">{{ t('drawing.move_cancel') }}</AppButton>
        </div>
    </Dialog>
</template>
