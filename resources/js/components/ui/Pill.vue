<script setup lang="ts">
import Icon from './Icon.vue';
import type { IconName } from './icons';

const props = withDefaults(defineProps<{ tone?: 'neutral' | 'warning' | 'success' | 'danger'; icon?: IconName }>(), { tone: 'neutral', icon: undefined });

const tones = {
    neutral: 'border-hairline bg-surface text-secondary',
    warning: 'border-warning-soft bg-warning-soft text-warning',
    success: 'border-success-soft bg-success-soft text-success',
    danger: 'border-danger-soft bg-danger-soft text-danger',
};
</script>

<template>
    <span :class="['inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11.5px] font-medium', tones[props.tone]]">
        <Icon v-if="props.icon" :name="props.icon" :size="12" />
        <slot />
    </span>
</template>
