<script setup lang="ts">
import Dialog from '../../components/overlays/Dialog.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';

defineProps<{ scope: 'domains' | 'activities' | null; count: number; name: string }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog v-model:open="open" :title="t('drawing_style.apply_title', { name })" :description="scope ? t(scope === 'domains' ? 'drawing_style.apply_body_domains' : 'drawing_style.apply_body_activities', { name, count }) : undefined">
        <div class="flex flex-wrap gap-2">
            <AppButton variant="primary" @click="emit('confirm')">{{ t('drawing_style.apply_confirm') }}</AppButton>
            <AppButton @click="open = false">{{ t('drawing_style.apply_cancel') }}</AppButton>
        </div>
    </Dialog>
</template>
