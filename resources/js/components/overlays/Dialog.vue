<script setup lang="ts">
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { nextTick, ref, watch } from 'vue';
import { t } from '../../i18n';

const props = defineProps<{ title: string; description?: string; dirty?: boolean; variant?: 'dialog' | 'panel' }>();
const open = defineModel<boolean>('open', { default: false });
const confirming = ref(false);
const keepEditing = ref<HTMLButtonElement>();

watch(open, () => (confirming.value = false));
watch(confirming, async (asking) => {
    if (!asking) return;
    await nextTick();
    keepEditing.value?.focus();
});

function requestClose(): void {
    if (props.dirty) confirming.value = true;
    else open.value = false;
}

function onOpenChange(next: boolean): void {
    if (next) open.value = true;
    else requestClose();
}

function discard(): void {
    confirming.value = false;
    open.value = false;
}
</script>

<template>
    <DialogRoot :open="open" @update:open="onOpenChange">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 bg-[#171B21]/40" />
            <DialogContent
                :class="variant === 'panel' ? 'fixed inset-y-0 right-0 w-[min(28rem,100vw)] overflow-y-auto' : 'fixed left-1/2 top-1/2 max-h-[90vh] w-[min(32rem,100vw)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto'"
                class="bg-white p-4 text-[#171B21] shadow-lg"
                @escape-key-down.prevent="requestClose"
                @pointer-down-outside.prevent="requestClose"
            >
                <DialogTitle class="font-[Space_Grotesk] text-base font-semibold">{{ title }}</DialogTitle>
                <DialogDescription v-if="description" class="mt-1 text-[12.5px] text-[#454C57]">{{ description }}</DialogDescription>
                <div class="mt-3"><slot /></div>
                <div v-if="confirming" role="alertdialog" aria-labelledby="discard-title" class="mt-3 rounded-md border border-[#848B95] p-3">
                    <p id="discard-title" class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
                    <div class="mt-2 flex gap-2">
                        <button ref="keepEditing" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="confirming = false">{{ t('dialog.keep_editing') }}</button>
                        <button type="button" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E]" @click="discard">{{ t('dialog.discard') }}</button>
                    </div>
                </div>
                <button type="button" class="absolute right-3 top-3 rounded-md border border-[#848B95] px-2 py-1 text-[12.5px]" @click="requestClose">{{ t('dialog.close') }}</button>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
