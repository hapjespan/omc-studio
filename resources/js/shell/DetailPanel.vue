<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import AppButton from '../components/ui/AppButton.vue';
import { t } from '../i18n';
import { detail, discardDetail, dismissDiscard, requestCloseDetail } from './detail';

const heading = ref<HTMLElement>();
const keepEditing = ref<InstanceType<typeof AppButton>>();

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
        <p v-if="detail.kicker" class="text-[11.5px] text-muted">{{ detail.kicker }}</p>
        <div class="flex items-start justify-between gap-2">
            <h2 id="detail-title" ref="heading" tabindex="-1" class="font-display text-[18px] font-semibold">{{ detail.title }}</h2>
            <AppButton size="small" @click="requestCloseDetail">{{ t('dialog.close') }}</AppButton>
        </div>
        <div v-if="detail.confirming" role="alertdialog" aria-labelledby="detail-discard" class="mt-3 rounded-control border border-hairline p-3">
            <p id="detail-discard" class="text-[13px] font-semibold">{{ t('dialog.discard_title') }}</p>
            <div class="mt-2 flex gap-2">
                <AppButton ref="keepEditing" size="dense" @click="dismissDiscard">{{ t('dialog.keep_editing') }}</AppButton>
                <AppButton variant="danger" size="dense" @click="discardDetail">{{ t('dialog.discard') }}</AppButton>
            </div>
        </div>
        <div id="detail-body" class="mt-3" />
    </aside>
</template>
