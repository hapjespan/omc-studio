<script setup lang="ts">
import EntryRow from './EntryRow.vue';
import type { EntryRowData } from './entry';

defineProps<{ rows: EntryRowData[]; letters: Record<string, string>; selectedId: string | null; label: string }>();
defineEmits<{ select: [id: string] }>();
</script>

<template>
    <ul :aria-label="label" class="mt-3 flex flex-col gap-2">
        <EntryRow v-for="row in rows" :key="row.element.id" :row="row" :letter="row.element.modality ? letters[row.element.modality] : null" :selected="row.element.id === selectedId" @select="$emit('select', $event)" />
    </ul>
</template>
