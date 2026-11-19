<script setup lang="ts">
import Icon from './Icon.vue';
import type { IconName } from './icons';

withDefaults(defineProps<{ label: string; icon: IconName; type?: 'button' | 'submit'; disabled?: boolean }>(), { type: 'button', disabled: false });
</script>

<template>
    <button :type="type" :disabled="disabled" :aria-label="label" class="inline-flex h-mark w-mark items-center justify-center rounded-badge border border-edge bg-white text-ink disabled:border-hairline disabled:bg-white disabled:text-muted">
        <Icon :name="icon" />
    </button>
</template>
