<script setup lang="ts">
import Dialog from '../../components/overlays/Dialog.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';

defineProps<{ open: boolean }>();
const emit = defineEmits<{ apply: []; reload: [] }>();
</script>

<template>
    <Dialog :open="open" :title="t('drawing.conflict_title')" :description="t('drawing.conflict_body')" @update:open="(next) => !next && emit('reload')">
        <div class="flex flex-wrap gap-2">
            <AppButton variant="primary" @click="emit('apply')">{{ t('drawing.conflict_apply') }}</AppButton>
            <AppButton @click="emit('reload')">{{ t('drawing.conflict_reload') }}</AppButton>
        </div>
    </Dialog>
</template>
