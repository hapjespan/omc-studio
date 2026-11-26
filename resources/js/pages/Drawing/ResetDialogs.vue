<script setup lang="ts">
import { computed } from 'vue';
import Dialog from '../../components/overlays/Dialog.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';
import type { ResetScope } from '../../edit-engine/commands/reset';

const props = defineProps<{ scope: ResetScope | null; name: string; count: number }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();

const title = computed(() => (props.scope === null ? '' : t(`drawing.reset_title_${props.scope}`, { name: props.name })));
</script>

<template>
    <Dialog :open="scope !== null" :title="title" :description="t('drawing.reset_body', { count: String(count) })" @update:open="(next) => !next && emit('cancel')">
        <div class="flex flex-wrap gap-2">
            <AppButton variant="primary" @click="emit('confirm')">{{ t('drawing.reset_confirm') }}</AppButton>
            <AppButton @click="emit('cancel')">{{ t('drawing.move_cancel') }}</AppButton>
        </div>
    </Dialog>
</template>
