<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 24 });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="shrink-0">
        <rect width="24" height="24" rx="6" class="fill-ink" />
        <rect x="6" y="6" width="5" height="5" rx="1" class="fill-white" />
        <rect x="13" y="6" width="5" height="5" rx="1" class="fill-white" />
        <rect x="6" y="13" width="5" height="5" rx="1" class="fill-white" />
        <rect x="13" y="13" width="5" height="5" rx="1" class="fill-white" />
    </svg>
</template>
