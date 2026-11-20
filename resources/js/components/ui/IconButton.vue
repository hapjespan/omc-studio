<script lang="ts">
const ICON_BUTTON_BASE = 'inline-flex items-center justify-center rounded-badge border border-edge bg-white text-ink disabled:border-hairline disabled:bg-white disabled:text-muted';

export const ICON_BUTTON_CLASS = `${ICON_BUTTON_BASE} h-mark w-mark`;
</script>

<script setup lang="ts">
import { ref } from 'vue';
import Icon from './Icon.vue';
import type { IconName } from './icons';

withDefaults(defineProps<{ label: string; icon: IconName; type?: 'button' | 'submit'; disabled?: boolean; size?: 28 | 36 }>(), { type: 'button', disabled: false, size: 28 });
const element = ref<HTMLButtonElement>();

defineExpose({ focus: () => element.value?.focus() });
</script>

<template>
    <button ref="element" :type="type" :disabled="disabled" :aria-label="label" :class="[ICON_BUTTON_BASE, size === 36 ? 'h-control w-control' : 'h-mark w-mark']">
        <Icon :name="icon" />
    </button>
</template>
