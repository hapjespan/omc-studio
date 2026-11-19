<script setup lang="ts">
import ModalityMark from '../../components/ModalityMark.vue';
import { t } from '../../i18n';
import { kindLabelKey, type EntryRowData } from './entry';
import ReorderButtons from './ReorderButtons.vue';

const depthClass = { 0: 'pl-0', 1: 'pl-6', 2: 'pl-12' } as const;

defineProps<{ row: EntryRowData; letter: string | null; selected: boolean; canvasId: number; icon?: string | null }>();
defineEmits<{ select: [id: string, opener: HTMLElement | null]; restore: [element: EntryRowData['element']] }>();
</script>

<template>
    <li data-testid="entry-row" :class="depthClass[row.depth]">
        <div class="flex items-center gap-2">
            <button type="button" :aria-current="selected ? 'true' : undefined" class="flex min-w-0 flex-1 items-baseline gap-3 rounded-md border border-[#848B95] px-3 py-1.5 text-left text-[13px] aria-[current=true]:border-2 aria-[current=true]:border-[#1B1F27]" @click="(event) => $emit('select', row.element.id, event.currentTarget as HTMLElement)">
                <span class="w-24 shrink-0 text-[12.5px] text-[#454C57]">{{ t(kindLabelKey(row.element.kind, row.element.area)) }}</span>
                <span class="min-w-0 flex-1 font-semibold">{{ row.element.name }}</span>
                <span v-if="row.element.archived" class="text-[12.5px] font-semibold text-[#454C57]">{{ t('list.archived') }}</span>
                <span v-if="icon" class="text-[12.5px] text-[#454C57]">{{ icon }}</span>
                <span v-if="row.empty" class="text-[12.5px] italic text-[#454C57]">{{ t('entry.empty') }}</span>
                <ModalityMark v-if="letter" :letter="letter" />
            </button>
            <button v-if="row.element.archived" type="button" class="rounded-md border border-[#848B95] px-2 py-1.5 text-[12.5px]" :aria-label="t('entry.restore_named', { name: row.element.name })" @click="$emit('restore', row.element)">{{ t('list.restore') }}</button>
            <ReorderButtons v-else :canvas-id="canvasId" :row="row" />
        </div>
    </li>
</template>
