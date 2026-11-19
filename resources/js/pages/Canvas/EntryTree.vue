<script setup lang="ts">
import AddLine from './AddLine.vue';
import EntryRow from './EntryRow.vue';
import type { EntryRowData } from './entry';

defineProps<{ rows: EntryRowData[]; letters: Record<string, string>; selectedId: string | null; label: string; canvasId: number; itemLabel: string; clusters: boolean }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null] }>();
</script>

<template>
    <ul :aria-label="label" class="mt-3 flex flex-col gap-2">
        <template v-for="row in rows" :key="row.element.id">
            <EntryRow :row="row" :canvas-id="canvasId" :letter="row.element.modality ? letters[row.element.modality] : null" :selected="row.element.id === selectedId" @select="(id, opener) => $emit('select', id, opener)" />
            <li v-if="row.closes" class="pl-6"><AddLine :canvas-id="canvasId" :parent="row.closes" :item-label="itemLabel" :clusters="clusters" /></li>
        </template>
    </ul>
</template>
