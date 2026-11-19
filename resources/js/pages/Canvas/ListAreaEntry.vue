<script setup lang="ts">
import EntryTree from './EntryTree.vue';
import type { EntryElement, EntryRowData } from './entry';

defineProps<{ canvasId: number; itemLabel: string; rows: EntryRowData[]; selectedId: string | null; label: string }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null]; restore: [element: EntryElement] }>();
</script>

<template>
    <EntryTree :rows="rows" :letters="{}" :selected-id="selectedId" :label="label" :canvas-id="canvasId" :item-label="itemLabel" :clusters="false" icons @select="(id, opener) => $emit('select', id, opener)" @restore="$emit('restore', $event)" />
</template>
