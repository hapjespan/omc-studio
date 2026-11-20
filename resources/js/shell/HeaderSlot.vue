<script setup lang="ts">
defineProps<{ to: 'title' | 'actions' }>();
</script>

<template>
    <Teleport defer :to="`#page-${to}`"><slot /></Teleport>
</template>
