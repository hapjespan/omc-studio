<script setup lang="ts">
import Dialog from '../../components/overlays/Dialog.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';

defineProps<{ open: boolean; failed: boolean }>();
const emit = defineEmits<{ retry: []; discard: []; stay: [] }>();
</script>

<template>
    <Dialog :open="open" :title="t('drawing.leave_title')" :description="t('drawing.leave_body')" @update:open="(next) => !next && emit('stay')">
        <p v-if="failed" class="mb-2 text-[13px] font-medium text-danger" role="alert">{{ t('drawing.save_failed') }}</p>
        <div class="flex flex-wrap gap-2">
            <AppButton variant="primary" @click="emit('retry')">{{ t('drawing.retry') }}</AppButton>
            <AppButton variant="danger" @click="emit('discard')">{{ t('drawing.leave_discard') }}</AppButton>
        </div>
    </Dialog>
</template>
