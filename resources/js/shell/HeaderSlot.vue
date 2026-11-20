<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { useHeaderSlot } from './layout';

defineProps<{ to: 'title' | 'actions' }>();
const ready = ref(false);

useHeaderSlot();
onMounted(() => nextTick(() => (ready.value = true)));
</script>

<template>
    <Teleport v-if="ready" :to="`#page-${to}`"><slot /></Teleport>
</template>
