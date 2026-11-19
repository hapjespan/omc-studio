<script setup lang="ts">
import { t } from '../../i18n';
import AddLine from './AddLine.vue';
import EntryRow from './EntryRow.vue';
import UnknownAllocationRow from './UnknownAllocationRow.vue';
import type { EntryElement, EntryRowData } from './entry';

defineProps<{ rows: EntryRowData[]; letters: Record<string, string>; selectedId: string | null; label: string; canvasId: number; itemLabel: string; clusters: boolean; icons?: boolean }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null]; restore: [element: EntryElement] }>();
</script>

<template>
    <ul :aria-label="label" class="mt-3 flex flex-col gap-2">
        <template v-for="row in rows" :key="row.element.id">
            <UnknownAllocationRow v-if="row.element.unknownAllocation" :row="row" :selected="row.element.id === selectedId" @select="(id, opener) => $emit('select', id, opener)" />
            <EntryRow v-else :row="row" :canvas-id="canvasId" :icon="icons && row.element.icon ? t(`structure.icon.${row.element.icon}`) : null" :letter="row.element.modality ? letters[row.element.modality] : null" :selected="row.element.id === selectedId" @select="(id, opener) => $emit('select', id, opener)" @restore="$emit('restore', $event)" />
            <li v-if="row.closes" class="pl-6"><AddLine :canvas-id="canvasId" :parent="row.closes" :item-label="itemLabel" :clusters="clusters" /></li>
        </template>
    </ul>
</template>
