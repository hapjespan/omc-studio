<script setup lang="ts">
import Icon from './Icon.vue';

const props = withDefaults(defineProps<{ tone?: 'warning' | 'success' | 'danger'; label?: string }>(), { tone: 'warning', label: undefined });

const tones = { warning: 'bg-warning-soft text-warning', success: 'bg-success-soft text-success', danger: 'bg-danger-soft text-danger' };
</script>

<template>
    <span :class="['flex items-start gap-2 rounded-control p-2.5 text-[12.5px]', tones[props.tone]]">
        <Icon :name="props.tone === 'success' ? 'check' : 'warning'" class="mt-px" />
        <span class="min-w-0"><span v-if="props.label" class="font-semibold">{{ props.label }}</span> <slot /></span>
    </span>
</template>
