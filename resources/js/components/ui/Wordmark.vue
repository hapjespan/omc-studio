<script setup lang="ts">
import LogoMark from './LogoMark.vue';

defineProps<{ name: string }>();
</script>

<template>
    <span class="flex items-center gap-2 font-display text-card-title font-semibold text-ink"><LogoMark /><span>{{ name }}</span></span>
</template>
