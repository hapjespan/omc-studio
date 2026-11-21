<script setup lang="ts">
import { computed } from 'vue';
import ActionMenu, { type MenuAction } from '../components/ui/ActionMenu.vue';
import AppButton from '../components/ui/AppButton.vue';
import { t } from '../i18n';

export type CardAction = 'copy' | 'rename' | 'archive' | 'restore' | 'delete' | 'save_template';

const props = defineProps<{ name: string; archived: boolean; template?: boolean }>();
const emit = defineEmits<{ select: [action: CardAction] }>();

const items = computed<MenuAction[]>(() =>
    [
        { key: 'copy', label: t('canvas_card.copy'), shown: !props.template },
        { key: 'rename', label: t('canvas_card.rename'), shown: !props.archived },
        { key: 'save_template', label: t('entry.save_template'), shown: !props.template },
        { key: 'restore', label: t('canvas_card.restore'), shown: props.archived },
        { key: 'archive', label: t('canvas_card.archive'), shown: !props.archived && !props.template },
        { key: 'delete', label: t('canvas_card.delete'), shown: true },
    ].filter((item) => item.shown),
);
</script>

<template>
    <ActionMenu :items="items" align="start" :trigger-label="t('canvas_card.actions', { name })" @select="emit('select', $event as CardAction)">
        <template #trigger><AppButton class="self-start">{{ t('canvas_card.actions_label') }}</AppButton></template>
    </ActionMenu>
</template>
