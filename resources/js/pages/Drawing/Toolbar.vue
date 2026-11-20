<script setup lang="ts">
import AppButton from '../../components/ui/AppButton.vue';
import { t } from '../../i18n';

defineProps<{ tool: 'select' | 'pan'; zoom: number }>();
defineEmits<{ tool: [tool: 'select' | 'pan'] }>();
</script>

<template>
    <div role="toolbar" :aria-label="t('drawing.toolbar')" class="mx-auto flex w-fit max-w-full flex-wrap items-center justify-center gap-1 rounded-card border border-edge bg-white p-1">
        <AppButton size="dense" :aria-pressed="tool === 'select'" :variant="tool === 'select' ? 'primary' : 'secondary'" @click="$emit('tool', 'select')">{{ t('drawing.tool_select') }}</AppButton>
        <AppButton size="dense" :aria-pressed="tool === 'pan'" :variant="tool === 'pan' ? 'primary' : 'secondary'" @click="$emit('tool', 'pan')">{{ t('drawing.tool_pan') }}</AppButton>
        <AppButton size="dense" disabled>{{ t('drawing.tool_align') }}</AppButton>
        <AppButton size="dense" disabled>{{ t('drawing.tool_distribute') }}</AppButton>
        <span class="px-2 text-[13px] text-secondary">{{ t('drawing.zoom', { percent: Math.round(zoom * 100) }) }}</span>
    </div>
</template>
