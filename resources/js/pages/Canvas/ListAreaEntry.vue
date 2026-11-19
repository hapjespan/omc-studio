<script setup lang="ts">
import EntryTree from './EntryTree.vue';
import type { EntryRowData } from './entry';

defineProps<{ canvasId: number; itemLabel: string; rows: EntryRowData[]; selectedId: string | null; label: string }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null] }>();
</script>

<template>
    <EntryTree :rows="rows" :letters="{}" :selected-id="selectedId" :label="label" :canvas-id="canvasId" :item-label="itemLabel" :clusters="false" icons @select="(id, opener) => $emit('select', id, opener)" />
</template>
