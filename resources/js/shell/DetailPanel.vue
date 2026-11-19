<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { t } from '../i18n';
import { detail, discardDetail, dismissDiscard, requestCloseDetail } from './detail';

const heading = ref<HTMLElement>();
const keepEditing = ref<HTMLButtonElement>();

watch(
    () => detail.open,
    async (open) => {
        if (!open) return;
        await nextTick();
        heading.value?.focus();
    },
);
watch(
    () => detail.confirming,
    async (asking) => {
        if (!asking) return;
        await nextTick();
        keepEditing.value?.focus();
    },
);
</script>

<template>
    <aside id="detail-panel" :hidden="!detail.open" aria-labelledby="detail-title" class="shell-detail border-l border-[#848B95] bg-white p-4" @keydown.esc="detail.confirming ? dismissDiscard() : requestCloseDetail()">
        <div class="flex items-start justify-between gap-2">
            <h2 id="detail-title" ref="heading" tabindex="-1" class="font-[Space_Grotesk] text-base font-semibold">{{ detail.title }}</h2>
            <button type="button" class="rounded-md border border-[#848B95] px-2 py-1 text-[12.5px]" @click="requestCloseDetail">{{ t('dialog.close') }}</button>
        </div>
        <div v-if="detail.confirming" role="alertdialog" aria-labelledby="detail-discard" class="mt-3 rounded-md border border-[#848B95] p-3">
            <p id="detail-discard" class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
            <div class="mt-2 flex gap-2">
                <button ref="keepEditing" type="button" class="rounded-md border border-[#848B95] px-3 py-1.5 text-[13px]" @click="dismissDiscard">{{ t('dialog.keep_editing') }}</button>
                <button type="button" class="rounded-md border border-[#C0645A] px-3 py-1.5 text-[13px] text-[#A12A1E]" @click="discardDetail">{{ t('dialog.discard') }}</button>
            </div>
        </div>
        <div id="detail-body" class="mt-3" />
    </aside>
</template>
