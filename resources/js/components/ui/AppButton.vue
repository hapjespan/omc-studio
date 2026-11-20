<script setup lang="ts">
import { ref } from 'vue';
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
const sizes = { control: 'min-h-control px-3 text-[13px]', dense: 'min-h-dense px-3 text-[13px]', small: 'min-h-mark px-2.5 text-[12.5px]' };
const shape = 'inline-flex items-center justify-center gap-1.5 rounded-control py-1 font-medium disabled:border-edge disabled:bg-disabled disabled:text-secondary';
const element = ref<HTMLButtonElement>();

defineExpose({ focus: () => element.value?.focus() });
</script>

<template>
    <button ref="element" :type="props.type" :disabled="props.disabled" :class="[shape, variants[props.variant], sizes[props.size]]">
        <Icon v-if="props.icon" :name="props.icon" />
        <slot />
    </button>
</template>
