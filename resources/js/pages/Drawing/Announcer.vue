<script setup lang="ts">
defineProps<{ message: string }>();
</script>

<template>
    <p class="min-h-[1.5rem] text-[13px] text-secondary" role="status" aria-live="polite" data-announcer>{{ message }}</p>
</template>
