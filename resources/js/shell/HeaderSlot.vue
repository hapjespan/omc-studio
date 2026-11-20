<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { shellLayout } from './layout';

defineProps<{ to: 'title' | 'actions' }>();
const ready = ref(false);

shellLayout.headerSlots++;
onMounted(() => nextTick(() => (ready.value = true)));
onBeforeUnmount(() => shellLayout.headerSlots--);
</script>

<template>
    <Teleport v-if="ready" :to="`#page-${to}`"><slot /></Teleport>
</template>
