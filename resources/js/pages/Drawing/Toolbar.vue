<script setup lang="ts">
import { computed } from 'vue';
import AppButton from '../../components/ui/AppButton.vue';
import SegmentedControl from '../../components/ui/SegmentedControl.vue';
import { t } from '../../i18n';

defineProps<{ tool: 'select' | 'pan'; zoom: number }>();
const emit = defineEmits<{ tool: [tool: 'select' | 'pan'] }>();
const items = computed(() => [
    { key: 'select', label: t('drawing.tool_select') },
    { key: 'pan', label: t('drawing.tool_pan') },
]);
</script>

<template>
    <div role="toolbar" :aria-label="t('drawing.toolbar')" class="mx-auto flex w-fit max-w-full flex-wrap items-center justify-center gap-1 rounded-card border border-edge bg-white p-1">
        <SegmentedControl :items="items" :active="tool" :label="t('drawing.tool_group')" @select="emit('tool', $event as 'select' | 'pan')" />
        <AppButton size="dense" disabled>{{ t('drawing.tool_align') }}</AppButton>
        <AppButton size="dense" disabled>{{ t('drawing.tool_distribute') }}</AppButton>
        <span class="px-2 text-[13px] text-secondary">{{ t('drawing.zoom', { percent: Math.round(zoom * 100) }) }}</span>
    </div>
</template>
