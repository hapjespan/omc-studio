<script setup lang="ts">
import Icon from './Icon.vue';
import type { IconName } from './icons';

const props = withDefaults(defineProps<{ variant?: 'primary' | 'secondary' | 'danger'; size?: 'control' | 'dense' | 'small'; type?: 'button' | 'submit'; icon?: IconName; disabled?: boolean }>(), {
    variant: 'secondary',
    size: 'control',
    type: 'button',
    icon: undefined,
    disabled: false,
});

const variants = {
    primary: 'border border-ink bg-ink text-white',
    secondary: 'border border-edge bg-white text-ink',
    danger: 'border border-danger-edge bg-white text-danger',
};
const sizes = { control: 'h-control px-3', dense: 'h-dense px-3', small: 'h-mark px-2.5' };
const shape = 'inline-flex items-center justify-center gap-1.5 rounded-control text-[13px] font-medium disabled:border-edge disabled:bg-disabled disabled:text-secondary';
</script>

<template>
    <button :type="props.type" :disabled="props.disabled" :class="[shape, variants[props.variant], sizes[props.size]]">
        <Icon v-if="props.icon" :name="props.icon" />
        <slot />
    </button>
</template>
