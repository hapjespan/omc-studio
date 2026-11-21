<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import AppButton from '../components/ui/AppButton.vue';
import DiscardConfirm from '../components/ui/DiscardConfirm.vue';
import { t } from '../i18n';
import { detail, discardDetail, dismissDiscard, requestCloseDetail } from './detail';

const heading = ref<HTMLElement>();
const keepEditing = ref<InstanceType<typeof DiscardConfirm>>();

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
        <DiscardConfirm v-if="detail.confirming" ref="keepEditing" size="dense" @keep="dismissDiscard" @discard="discardDetail" />
        <div id="detail-body" class="mt-3" />
    </aside>
</template>
