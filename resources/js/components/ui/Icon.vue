<script setup lang="ts">
import { ICON_PATHS, type IconName } from './icons';

withDefaults(defineProps<{ name: IconName; size?: number }>(), { size: 16 });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="shrink-0"><path :d="ICON_PATHS[name]" /></svg>
</template>
