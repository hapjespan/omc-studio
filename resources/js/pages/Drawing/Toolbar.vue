<script setup lang="ts">
import { computed } from 'vue';
import type { DrawingCommand } from '../../edit-engine';
import ActionMenu from '../../components/ui/ActionMenu.vue';
import AppButton from '../../components/ui/AppButton.vue';
import IconButton from '../../components/ui/IconButton.vue';
import SegmentedControl from '../../components/ui/SegmentedControl.vue';
import { t } from '../../i18n';
import type { NavigateAction } from './navigate';

const props = defineProps<{ tool: 'select' | 'pan'; zoom: number; count: number }>();
const emit = defineEmits<{ tool: [tool: 'select' | 'pan']; command: [command: DrawingCommand]; navigate: [action: NavigateAction] }>();

const items = computed(() => [
    { key: 'select', label: t('drawing.tool_select') },
    { key: 'pan', label: t('drawing.tool_pan') },
]);
const alignItems = computed(() =>
    ['horizontal:start', 'horizontal:centre', 'horizontal:end', 'vertical:start', 'vertical:centre', 'vertical:end'].map((key) => ({ key, label: t(`drawing.align_${key.replace(':', '_')}`), disabled: props.count < 1 })),
);
const distributeItems = computed(() => ['horizontal', 'vertical'].map((key) => ({ key, label: t(`drawing.distribute_${key}`), disabled: props.count < 3 })));

const align = (key: string): void => {
    const [axis, to] = key.split(':') as ['horizontal' | 'vertical', 'start' | 'centre' | 'end'];
    emit('command', { kind: 'align', axis, to });
};
</script>

<template>
    <div role="toolbar" :aria-label="t('drawing.toolbar')" class="mx-auto flex w-fit max-w-full flex-wrap items-center justify-center gap-1 rounded-card border border-edge bg-white p-1">
        <SegmentedControl :items="items" :active="tool" :label="t('drawing.tool_group')" @select="emit('tool', $event as 'select' | 'pan')" />
        <ActionMenu :label="t('drawing.tool_align')" :items="alignItems" @select="align" />
        <ActionMenu :label="t('drawing.tool_distribute')" :items="distributeItems" @select="emit('command', { kind: 'distribute', axis: $event as 'horizontal' | 'vertical' })" />
        <IconButton icon="minus" :label="t('view.zoom.out')" @click="emit('navigate', 'out')" />
        <output class="px-2 text-[13px] text-secondary" aria-live="polite">{{ t('drawing.zoom', { percent: Math.round(zoom * 100) }) }}</output>
        <IconButton icon="plus" :label="t('view.zoom.in')" @click="emit('navigate', 'in')" />
        <AppButton size="small" @click="emit('navigate', 'actual')">{{ t('drawing.zoom_100') }}</AppButton>
        <IconButton icon="chevron-left" :label="t('drawing.pan_left')" @click="emit('navigate', 'left')" />
        <IconButton icon="chevron-right" :label="t('drawing.pan_right')" @click="emit('navigate', 'right')" />
        <IconButton icon="chevron-up" :label="t('drawing.pan_up')" @click="emit('navigate', 'up')" />
        <IconButton icon="chevron-down" :label="t('drawing.pan_down')" @click="emit('navigate', 'down')" />
    </div>
</template>
