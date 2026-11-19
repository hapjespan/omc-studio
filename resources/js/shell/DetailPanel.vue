<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import AppButton from '../components/ui/AppButton.vue';
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
    <aside id="detail-panel" :hidden="!detail.open" aria-labelledby="detail-title" class="shell-detail overflow-y-auto rounded-panel border border-hairline bg-white p-4" @keydown.esc="detail.confirming ? dismissDiscard() : requestCloseDetail()">
        <div class="flex items-start justify-between gap-2">
            <h2 id="detail-title" ref="heading" tabindex="-1" class="font-display text-[18px] font-semibold">{{ detail.title }}</h2>
            <AppButton size="small" @click="requestCloseDetail">{{ t('dialog.close') }}</AppButton>
        </div>
        <div v-if="detail.confirming" role="alertdialog" aria-labelledby="detail-discard" class="mt-3 rounded-control border border-hairline p-3">
            <p id="detail-discard" class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
            <div class="mt-2 flex gap-2">
                <button ref="keepEditing" type="button" class="inline-flex h-dense items-center rounded-control border border-edge bg-white px-3 text-[13px] font-medium" @click="dismissDiscard">{{ t('dialog.keep_editing') }}</button>
                <button type="button" class="inline-flex h-dense items-center rounded-control border border-danger-edge bg-white px-3 text-[13px] font-medium text-danger" @click="discardDetail">{{ t('dialog.discard') }}</button>
            </div>
        </div>
        <div id="detail-body" class="mt-3" />
    </aside>
</template>
